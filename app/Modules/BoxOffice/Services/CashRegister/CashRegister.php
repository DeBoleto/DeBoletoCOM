<?php

namespace App\Modules\BoxOffice\Services\CashRegister;

use App\Modules\BoxOffice\Models\CajaApertura;
use App\Modules\BoxOffice\Models\CajaAperturaCompra;
use App\Modules\BoxOffice\Support\Money;
use App\Modules\Orders\Enums\PaymentType;
use App\Modules\Orders\Models\EventosCompras;
use App\Modules\Shared\Models\LegacyModel;
use App\Modules\Users\Models\Usuarios;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Apertura, ventas y cierre de la caja de un cajero. Solo puede haber una
 * caja abierta por cajero; cada venta de taquilla queda ligada a ella.
 */
class CashRegister
{
    public function current(Usuarios $cashier): ?CajaApertura
    {
        return CajaApertura::query()->abiertaDe($cashier->pk_usuario)->first();
    }

    public function open(Usuarios $cashier, float $amount, ?string $name, ?string $comment): CajaApertura
    {
        if ($this->current($cashier) !== null) {
            throw ValidationException::withMessages(['monto_apertura' => 'Ya tienes una caja abierta.']);
        }

        $register = new CajaApertura;
        $register->forceFill([
            'pk_usuario' => $cashier->pk_usuario,
            'caja' => $name,
            'estatus' => CajaApertura::STATUS_OPEN,
            'apertura_fecha' => Carbon::now(LegacyModel::legacyTimezone()),
            'monto_apertura' => Money::round($amount),
            'comentario_apertura' => $comment,
            'efectivo_esperado' => Money::round($amount),
        ]);

        try {
            $register->save();
        } catch (QueryException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                throw ValidationException::withMessages(['monto_apertura' => 'Ya tienes una caja abierta.']);
            }

            throw $exception;
        }

        return $register;
    }

    public function attach(CajaApertura $register, EventosCompras $purchase): void
    {
        $link = new CajaAperturaCompra;
        $link->forceFill([
            'pk_caja_apertura' => $register->pk_caja_apertura,
            'pk_evento_compra' => $purchase->pk_evento_compra,
        ])->save();
    }

    /**
     * Compras de la caja con lo cobrado al cliente (precio + comisiones de
     * los boletos que no son cortesía) y totales por forma de pago.
     *
     * @return array{purchases: Collection<int, array<string, mixed>>, totals: array<string, float|int>}
     */
    public function summary(CajaApertura $register): array
    {
        $purchases = DB::table('cajas_aperturas_compras as cc')
            ->join('eventos_compras as c', 'c.pk_evento_compra', '=', 'cc.pk_evento_compra')
            ->leftJoin('eventos as e', 'e.pk_evento', '=', 'c.pk_evento')
            ->leftJoin('eventos_funciones_boletos as b', fn ($join) => $join
                ->on('b.pk_evento_compra', '=', 'c.pk_evento_compra')
                ->where('b.ocultar', 0))
            ->where('cc.pk_caja_apertura', $register->pk_caja_apertura)
            ->groupBy('c.pk_evento_compra', 'c.pk_evento_compra_tipo', 'c.creacion_fecha', 'e.evento')
            ->orderBy('c.pk_evento_compra')
            ->selectRaw('c.pk_evento_compra, c.pk_evento_compra_tipo, c.creacion_fecha, e.evento')
            ->selectRaw('COUNT(b.pk_evento_funcion_boleto) as boletos')
            ->selectRaw('COALESCE(SUM(b.cortesia), 0) as cortesias')
            ->selectRaw('COALESCE(SUM(CASE WHEN b.cortesia = 0 THEN b.precio + COALESCE(b.comision_deboleto, 0) + COALESCE(b.comision_servicio, 0) ELSE 0 END), 0) as total')
            ->get()
            ->map(fn (object $row) => [
                'id' => (int) $row->pk_evento_compra,
                'event' => $row->evento,
                'date' => Carbon::parse($row->creacion_fecha)->format('d/m/Y H:i'),
                'type' => (int) $row->pk_evento_compra_tipo,
                'typeLabel' => PaymentType::tryFrom((int) $row->pk_evento_compra_tipo)?->label() ?? 'Otro',
                'tickets' => (int) $row->boletos,
                'courtesies' => (int) $row->cortesias,
                'total' => Money::round((float) $row->total),
            ]);

        $byType = fn (PaymentType $type) => Money::round($purchases->where('type', $type->value)->sum('total'));
        $cash = $byType(PaymentType::Cash);

        return [
            'purchases' => $purchases,
            'totals' => [
                'purchases' => $purchases->count(),
                'tickets' => $purchases->sum('tickets'),
                'cash' => $cash,
                'terminal' => $byType(PaymentType::Terminal),
                'transfer' => $byType(PaymentType::Transfer),
                'sales' => Money::round($purchases->sum('total')),
                'expectedCash' => Money::round($register->monto_apertura + $cash),
            ],
        ];
    }

    public function close(CajaApertura $register, Usuarios $authorizer, float $countedCash, ?string $comment): CajaApertura
    {
        return DB::transaction(function () use ($register, $authorizer, $countedCash, $comment) {
            $locked = CajaApertura::query()->lockForUpdate()->findOrFail($register->pk_caja_apertura);

            if (! $locked->isOpen()) {
                throw ValidationException::withMessages(['efectivo_contado' => 'Esta caja ya está cerrada.']);
            }

            $totals = $this->summary($locked)['totals'];
            $counted = Money::round($countedCash);

            $locked->forceFill([
                'estatus' => CajaApertura::STATUS_CLOSED,
                'cierre_fecha' => Carbon::now(LegacyModel::legacyTimezone()),
                'cierre_pk_usuario_autoriza' => $authorizer->pk_usuario,
                'total_compras' => $totals['purchases'],
                'total_boletos' => $totals['tickets'],
                'total_efectivo' => $totals['cash'],
                'total_terminal' => $totals['terminal'],
                'total_transferencia' => $totals['transfer'],
                'total_ventas' => $totals['sales'],
                'efectivo_esperado' => $totals['expectedCash'],
                'efectivo_contado' => $counted,
                'diferencia' => Money::round($counted - $totals['expectedCash']),
                'comentario_cierre' => $comment,
            ])->save();

            return $locked;
        });
    }
}
