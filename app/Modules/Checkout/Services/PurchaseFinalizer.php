<?php

namespace App\Modules\Checkout\Services;

use App\Modules\Checkout\Events\WebPurchasePaid;
use App\Modules\Events\Models\EventoReservaBoleto;
use App\Modules\Orders\Enums\PaymentType;
use App\Modules\Orders\Enums\PurchaseStatus;
use App\Modules\Orders\Models\ContraCargoCompra;
use App\Modules\Orders\Models\EventosCompraLog;
use App\Modules\Orders\Models\EventosCompras;
use App\Modules\Payments\Data\Charge;
use App\Modules\Shared\Models\LegacyModel;
use App\Modules\Tickets\Enums\TicketStatus;
use App\Modules\Tickets\Models\EventoEstacionamientoBoleto;
use App\Modules\Tickets\Models\EventosFuncionesBoletos;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cierra o libera una compra web según el estado real del cargo. Todas las
 * operaciones son idempotentes: el regreso del 3D Secure, el webhook y la
 * tarea programada pueden llegar en cualquier orden.
 */
class PurchaseFinalizer
{
    /**
     * Marca la compra como pagada y emite los boletos.
     *
     * @return bool true si esta llamada fue la que cerró la compra.
     */
    public function markPaid(EventosCompras $purchase, Charge $charge): bool
    {
        if (! $charge->isPaid()) {
            return false;
        }

        $paid = DB::transaction(function () use ($purchase, $charge) {
            $locked = EventosCompras::query()->whereKey($purchase->pk_evento_compra)->lockForUpdate()->firstOrFail();

            if ($locked->status() === PurchaseStatus::Paid) {
                return false;
            }

            $snapshot = $this->snapshot($locked);

            if (! $this->chargeMatches($locked, $charge, $snapshot)) {
                return false;
            }

            if (! $locked->status()?->isPending()) {
                Log::critical('Cobro completado de una compra web ya liberada; requiere reembolso o reasignación manual.', [
                    'compra' => $locked->pk_evento_compra,
                    'cargo' => $charge->id,
                    'estatus' => $locked->estatus,
                ]);

                return false;
            }

            EventosFuncionesBoletos::query()
                ->where('pk_evento_compra', $locked->pk_evento_compra)
                ->where('pk_evento_funcion_boleto_status', TicketStatus::Pending->value)
                ->update([
                    'pk_evento_funcion_boleto_status' => TicketStatus::Issued->value,
                    'modificacion_pk_usuario' => $locked->pk_usuario,
                    'modificacion_fecha' => Carbon::now(LegacyModel::legacyTimezone()),
                ]);

            $locked->forceFill(['estatus' => PurchaseStatus::Paid->value, 'pk_openpay' => $charge->id] + $this->cardColumns($charge))->save();

            if (isset($snapshot['owner'])) {
                EventoReservaBoleto::releaseFor($snapshot['owner']);
            }

            $this->recordResponse($charge);

            return true;
        });

        if ($paid) {
            WebPurchasePaid::dispatch($purchase->pk_evento_compra, $this->snapshot($purchase)['whatsapp'] ?? null);
        }

        return $paid;
    }

    /**
     * Libera boletos, marcadores de mesa y estacionamiento de una compra que no se pagó.
     *
     * @return bool true si la compra seguía pendiente y se liberó.
     */
    public function release(EventosCompras $purchase, PurchaseStatus $status = PurchaseStatus::Rejected): bool
    {
        return DB::transaction(function () use ($purchase, $status) {
            $locked = EventosCompras::query()->whereKey($purchase->pk_evento_compra)->lockForUpdate()->firstOrFail();

            if (! $locked->status()?->isPending() || $locked->pk_evento_compra_tipo !== PaymentType::Web->value) {
                return false;
            }

            $markers = array_map('intval', (array) ($this->snapshot($locked)['markers'] ?? []));
            $purchaseIds = [$locked->pk_evento_compra, ...$markers];

            EventosFuncionesBoletos::query()->whereIn('pk_evento_compra', $purchaseIds)->delete();
            EventoEstacionamientoBoleto::query()->where('pk_evento_compra', $locked->pk_evento_compra)->delete();
            EventosCompras::query()->whereIn('pk_evento_compra', $markers)->delete();

            $locked->forceFill(['estatus' => $status->value])->save();

            return true;
        });
    }

    public function chargebackOpened(EventosCompras $purchase, string $chargeId, array $payload): void
    {
        ContraCargoCompra::query()->firstOrCreate(
            ['pk_openpay' => $chargeId],
            ['pk_evento_compra' => $purchase->pk_evento_compra, 'json_respuesta' => json_encode($payload)],
        );
    }

    public function chargebackRejected(string $chargeId): void
    {
        ContraCargoCompra::query()->where('pk_openpay', $chargeId)->update(['estatus' => ContraCargoCompra::STATUS_REJECTED]);
    }

    /**
     * Contracargo aceptado: los boletos dejan de ser válidos para el acceso.
     */
    public function chargebackAccepted(EventosCompras $purchase, string $chargeId): void
    {
        DB::transaction(function () use ($purchase, $chargeId) {
            EventosFuncionesBoletos::query()
                ->where('pk_evento_compra', $purchase->pk_evento_compra)
                ->update(['pk_evento_funcion_boleto_status' => TicketStatus::ChargedBack->value]);

            $purchase->forceFill(['estatus' => PurchaseStatus::ChargedBack->value])->save();

            ContraCargoCompra::query()->where('pk_openpay', $chargeId)->update(['estatus' => ContraCargoCompra::STATUS_ACCEPTED]);
        });
    }

    /**
     * Datos de la compra guardados al crearla (dueño de la retención, monto, marcadores…).
     */
    public function snapshot(EventosCompras $purchase): array
    {
        $log = $this->log($purchase);

        return $log ? (array) json_decode((string) $log->json_compra, true) : [];
    }

    public function log(EventosCompras $purchase): ?EventosCompraLog
    {
        return EventosCompraLog::query()
            ->where('pk_usuario', $purchase->pk_usuario)
            ->where('json_compra', 'like', '%"purchase_code":"'.$purchase->codigo.'"%')
            ->latest('pk_evento_compra_log')
            ->first();
    }

    /**
     * El cargo debe ser de esta orden y por el monto calculado al crearla.
     */
    private function chargeMatches(EventosCompras $purchase, Charge $charge, array $snapshot): bool
    {
        $expected = (float) ($snapshot['amount'] ?? -1);
        $matches = $charge->orderId === $purchase->codigo && abs($charge->amount - $expected) < 0.01;

        if (! $matches) {
            Log::critical('El cargo no corresponde a la compra web.', [
                'compra' => $purchase->pk_evento_compra,
                'cargo' => $charge->id,
                'monto' => $charge->amount,
                'esperado' => $expected,
            ]);
        }

        return $matches;
    }

    private function cardColumns(Charge $charge): array
    {
        if ($charge->card === null) {
            return [];
        }

        return [
            'tarjeta_banco' => $charge->card->bank,
            'tarjeta_propietario' => $charge->card->holderName,
            'tarjeta_numero' => $charge->card->maskedNumber,
            'tarjeta_mes_expiracion' => $charge->card->expirationMonth,
            'tarjeta_ano_expiracion' => $charge->card->expirationYear,
        ];
    }

    private function recordResponse(Charge $charge): void
    {
        EventosCompraLog::query()->where('pk_openpay', $charge->id)->update([
            'json_respuesta' => json_encode([
                'id' => $charge->id,
                'status' => $charge->status->value,
                'amount' => $charge->amount,
                'order_id' => $charge->orderId,
                'method' => $charge->method,
            ]),
        ]);
    }
}
