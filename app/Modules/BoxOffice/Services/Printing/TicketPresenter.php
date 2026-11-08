<?php

namespace App\Modules\BoxOffice\Services\Printing;

use App\Modules\BoxOffice\Support\Money;
use App\Modules\Events\Enums\CommissionType;
use App\Modules\Events\Models\EventoLocalidadAsiento;
use App\Modules\Events\Models\Views\EventoFuncionVista;
use App\Modules\Events\Models\Views\EventoLocalidadVista;
use App\Modules\Events\Models\Views\EventoVista;
use App\Modules\Orders\Enums\PaymentType;
use App\Modules\Orders\Models\EventosCompras;
use App\Modules\Orders\Models\EventosComprasTipos;
use App\Modules\Tickets\Models\EventosFuncionesBoletos;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Arma los datos que consumen los formatos de impresión y el boleto digital.
 *
 * Cada flujo legacy mostraba la comisión de forma distinta; se conserva como
 * estrategias explícitas:
 *  - STORED:   la guardada en el boleto (venta de taquilla, cortesías).
 *  - DIGITAL:  recalculada; venta web con cargo de pasarela solo en comisión fija.
 *  - EXCHANGE: recalculada; venta web con cargo de pasarela en todos los tipos.
 *  - BOX:      recalculada con la comisión de taquilla (cambio de boletos).
 */
class TicketPresenter
{
    public const STORED = 'stored';

    public const DIGITAL = 'digital';

    public const EXCHANGE = 'exchange';

    public const BOX = 'box';

    /**
     * @return array{evento: EventoVista, funcion: EventoFuncionVista, localidad: EventoLocalidadVista, boletos: list<array<string, mixed>>}
     */
    public function forPurchase(EventosCompras $purchase, string $strategy = self::STORED): array
    {
        $tickets = $purchase->boletos()->with('localidadEspecial')->orderBy('pk_evento_funcion_boleto')->get();

        return $this->present($tickets, $strategy, $purchase->pk_evento_compra_tipo === PaymentType::Web->value, $purchase);
    }

    /**
     * @param  Collection<int, EventosFuncionesBoletos>  $tickets
     */
    public function forTickets(Collection $tickets, string $strategy = self::STORED): array
    {
        return $this->present($tickets->load('localidadEspecial')->sortBy('pk_evento_funcion_boleto')->values(), $strategy, false);
    }

    private function present(Collection $tickets, string $strategy, bool $isWeb, ?EventosCompras $purchase = null): array
    {
        abort_if($tickets->isEmpty(), 404);

        $event = EventoVista::query()->findOrFail($tickets->first()->pk_evento);
        $functions = EventoFuncionVista::query()
            ->whereIn('pk_evento_funcion', $tickets->pluck('pk_evento_funcion')->unique())
            ->get()
            ->keyBy('pk_evento_funcion');
        $localities = EventoLocalidadVista::query()
            ->whereIn('pk_evento_localidad', $tickets->pluck('pk_evento_localidad')->unique())
            ->get()
            ->keyBy('pk_evento_localidad');
        $tables = EventoLocalidadAsiento::query()
            ->whereIn('pk_evento_localidad', $tickets->pluck('pk_evento_localidad')->unique())
            ->pluck('pk_evento_localidad');
        $folios = $purchase ? $this->folios($purchase) : [];
        $type = CommissionType::tryFrom((int) $event->pk_evento_comision_tipo);
        $webRate = $strategy === self::STORED ? 0.0 : (float) EventosComprasTipos::query()->whereKey(PaymentType::Web->value)->value('comision_porcentaje');

        $rows = $tickets->map(function (EventosFuncionesBoletos $ticket) use ($functions, $localities, $tables, $folios, $strategy, $type, $isWeb, $webRate) {
            $function = $functions->get($ticket->pk_evento_funcion);
            $locality = $localities->get($ticket->pk_evento_localidad);
            $commission = $ticket->cortesia && $strategy !== self::BOX
                ? 0.0
                : $this->commission($strategy, $type, $isWeb, $ticket, $locality, $webRate);
            $special = $ticket->localidadEspecial;

            return [
                'pk_evento_funcion_boleto' => $ticket->pk_evento_funcion_boleto,
                'pk_evento_compra' => $ticket->pk_evento_compra,
                'pk_evento_localidad' => $ticket->pk_evento_localidad,
                'funcion_fecha' => $function?->fecha,
                'funcion_fecha_natural' => $function?->fecha_natural,
                'funcion_hora' => $function?->hora_natural,
                'localidad' => $locality?->evento_localidad,
                'localidad_text' => $special?->evento_localidad ?? '',
                'localidad_id' => $special?->pk_evento_localidad_especiales ?? 0,
                'asiento' => $ticket->asiento,
                'codigo' => $ticket->codigo,
                'folio' => $folios[$ticket->pk_evento_funcion_boleto] ?? $ticket->pk_evento_funcion_boleto,
                'cortesia' => (int) $ticket->cortesia,
                'precio' => Money::format($ticket->precio),
                'comision' => Money::format($commission),
                'total' => Money::format($ticket->precio + $commission),
                'mesas' => $tables->contains($ticket->pk_evento_localidad),
            ];
        });

        return [
            'evento' => $event,
            'funcion' => $functions->get($tickets->first()->pk_evento_funcion),
            'localidad' => $localities->get($tickets->last()->pk_evento_localidad),
            'boletos' => $rows->values()->all(),
        ];
    }

    private function commission(string $strategy, ?CommissionType $type, bool $isWeb, EventosFuncionesBoletos $ticket, ?EventoLocalidadVista $locality, float $webRate): float
    {
        if ($strategy === self::STORED || $locality === null) {
            return (float) $ticket->comision_deboleto;
        }

        $web = $isWeb && $strategy !== self::BOX;
        $base = $web ? $locality->comisionWeb : $locality->comision;
        $service = match ($type) {
            CommissionType::Percentage, CommissionType::PercentageNoCardFee => $ticket->precio * ($base / 100),
            CommissionType::Fixed, CommissionType::FixedNoCardFee => $base,
            default => 0.0,
        };

        $gateway = $web && ($strategy === self::EXCHANGE || $type === CommissionType::Fixed)
            ? $this->gatewayFee($ticket->precio + $service, $webRate)
            : 0.0;

        return $service + $gateway;
    }

    private function gatewayFee(float $amount, float $rate): float
    {
        $fixed = (float) config('boxoffice.gateway_fixed_fee', 2.5);
        $bank = ($rate / 100) * $amount;

        return $bank + (($bank + $fixed) * (float) config('boxoffice.vat_rate', 0.16)) + $fixed;
    }

    /**
     * Folio consecutivo del boleto dentro del evento, en una sola consulta.
     *
     * @return array<int, int>
     */
    private function folios(EventosCompras $purchase): array
    {
        return DB::table('eventos_funciones_boletos as b')
            ->where('b.pk_evento_compra', $purchase->pk_evento_compra)
            ->select('b.pk_evento_funcion_boleto')
            ->selectSub(function ($query) {
                $query->from('eventos_funciones_boletos as e')
                    ->whereColumn('e.pk_evento', 'b.pk_evento')
                    ->whereColumn('e.pk_evento_funcion_boleto', '<=', 'b.pk_evento_funcion_boleto')
                    ->selectRaw('count(*)');
            }, 'folio')
            ->pluck('folio', 'pk_evento_funcion_boleto')
            ->map(fn ($folio) => (int) $folio)
            ->all();
    }
}
