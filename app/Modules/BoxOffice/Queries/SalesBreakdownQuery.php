<?php

namespace App\Modules\BoxOffice\Queries;

use App\Modules\Orders\Enums\PaymentType;
use App\Modules\Tickets\Enums\TicketStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Agregados de boletos vendidos para cortes de caja y reportes de ventas.
 * Port parametrizado de tablaTiposCompras / tablaTiposComprasCajas /
 * tablaBoletosVendidos / tablaBoletosCortesia.
 */
class SalesBreakdownQuery
{
    /**
     * Ventas por forma de pago agrupadas por función, localidad y precio.
     *
     * @param  bool  $cashCut  Variante de corte de caja: incluye boletos aún no entregados.
     */
    public function byPaymentType(
        int $eventId,
        PaymentType $type,
        ?int $sellerId,
        ?Carbon $from,
        ?Carbon $to,
        bool $cashCut,
        bool $paidOnly = false,
        bool $defenderOnly = false,
    ): Collection {
        $hasDates = $from !== null && $to !== null;

        return DB::table('eventos_funciones_boletos as b')
            ->join('eventos_compras as c', 'c.pk_evento_compra', '=', 'b.pk_evento_compra')
            ->join('eventos_localidades as l', 'l.pk_evento_localidad', '=', 'b.pk_evento_localidad')
            ->where('b.ocultar', 0)
            ->where('b.pk_evento', $eventId)
            ->where('c.pk_evento_compra_tipo', $type->value)
            ->when($sellerId !== null, fn (Builder $query) => $query->where('b.adquisicion_pk_usuario', $sellerId))
            ->when(! $cashCut, fn (Builder $query) => $query->where('b.pk_evento_funcion_boleto_status', TicketStatus::Issued->value))
            ->when($cashCut || $hasDates, fn (Builder $query) => $query->where('c.estatus', 0))
            ->when($paidOnly, fn (Builder $query) => $query->where('b.precio', '>', 0))
            ->when($defenderOnly, fn (Builder $query) => $query->where('c.total_defender', '>', 0))
            ->tap(fn (Builder $query) => $this->acquiredBetween($query, $from, $to))
            ->groupBy('b.pk_evento_funcion', 'b.pk_evento_localidad', 'l.evento_localidad', 'b.precio', 'l.comision', 'l.precio')
            ->select([
                'b.pk_evento_funcion', 'b.pk_evento_localidad', 'l.evento_localidad', 'b.precio', 'l.comision',
            ])
            ->selectRaw('SUM(b.comision_deboleto) as total_comision')
            ->selectRaw('SUM(b.comision_servicio) as total_servicio')
            ->selectRaw('COUNT(b.pk_evento_funcion_boleto) as total_vendidos')
            ->selectRaw('COUNT(DISTINCT c.pk_evento_compra) as total_compras')
            ->selectRaw('ROUND(((b.precio - l.precio) / NULLIF(l.precio, 0)) * 100, 2) * -1 as porcentaje_diferencia')
            ->get();
    }

    /**
     * Boletos pagados entregados, por función, localidad y precio.
     */
    public function sold(int $eventId, ?int $sellerId, ?Carbon $from, ?Carbon $to): Collection
    {
        return DB::table('eventos_funciones_boletos as b')
            ->join('eventos_localidades as l', 'l.pk_evento_localidad', '=', 'b.pk_evento_localidad')
            ->where('b.ocultar', 0)
            ->where('b.pk_evento', $eventId)
            ->where('b.pk_evento_funcion_boleto_status', TicketStatus::Issued->value)
            ->where('b.cortesia', 0)
            ->when($sellerId !== null, fn (Builder $query) => $query->where('b.adquisicion_pk_usuario', $sellerId))
            ->tap(fn (Builder $query) => $this->acquiredBetween($query, $from, $to))
            ->groupBy('b.pk_evento_funcion', 'b.pk_evento_localidad', 'b.precio', 'l.precio')
            ->select(['b.pk_evento_funcion', 'b.pk_evento_localidad', 'b.precio'])
            ->selectRaw('l.precio as precio_lista')
            ->selectRaw('COUNT(b.pk_evento_funcion_boleto) as total_boletos_vendidos')
            ->get();
    }

    /**
     * Cortesías no bloqueadas por función y localidad (todas las taquillas).
     *
     * @return Collection<string, int> "funcion:localidad" => cantidad
     */
    public function courtesies(int $eventId, ?Carbon $from, ?Carbon $to): Collection
    {
        return DB::table('eventos_funciones_boletos as b')
            ->where('b.ocultar', 0)
            ->where('b.pk_evento', $eventId)
            ->where('b.pk_evento_funcion_boleto_status', '!=', TicketStatus::Blocked->value)
            ->where('b.cortesia', 1)
            ->tap(fn (Builder $query) => $this->acquiredBetween($query, $from, $to))
            ->groupBy('b.pk_evento_funcion', 'b.pk_evento_localidad')
            ->selectRaw("CONCAT(b.pk_evento_funcion, ':', b.pk_evento_localidad) as bucket, COUNT(*) as total")
            ->pluck('total', 'bucket')
            ->map(fn ($total) => (int) $total);
    }

    private function acquiredBetween(Builder $query, ?Carbon $from, ?Carbon $to): void
    {
        if ($from === null || $to === null) {
            return;
        }

        $query->where('b.adquisicion_fecha', '>=', $from->copy()->startOfDay()->toDateTimeString())
            ->where('b.adquisicion_fecha', '<', $to->copy()->addDay()->startOfDay()->toDateTimeString());
    }
}
