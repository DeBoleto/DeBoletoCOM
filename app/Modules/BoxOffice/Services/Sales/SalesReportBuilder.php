<?php

namespace App\Modules\BoxOffice\Services\Sales;

use App\Modules\BoxOffice\Queries\SalesBreakdownQuery;
use App\Modules\Events\Enums\CommissionType;
use App\Modules\Events\Models\EventosLocalidades;
use App\Modules\Events\Models\Views\EventoFuncionVista;
use App\Modules\Events\Models\Views\EventoVista;
use App\Modules\Orders\Enums\PaymentType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Datos de los PDFs de corte de caja y ventas por fecha, agrupados por función.
 */
class SalesReportBuilder
{
    private const BOX_OFFICE_TYPES = [PaymentType::Cash, PaymentType::Terminal, PaymentType::Transfer];

    public function __construct(private readonly SalesBreakdownQuery $query) {}

    /**
     * Corte de caja del día del vendedor (comisiones tal como se guardaron).
     */
    public function cashCut(EventoVista $event, int $sellerId, Carbon $date): array
    {
        $tables = $this->tablesByType($event, $sellerId, $date, $date, cashCut: true, commission: fn ($row) => (float) $row->total_comision);

        return $this->byFunction($event, fn (int $functionId) => $this->sections($tables, $functionId));
    }

    /**
     * Corte de un vendedor en una fecha, con la comisión recalculada según el
     * tipo de comisión del evento (como el reporte legacy por usuario).
     */
    public function sellerCut(EventoVista $event, int $sellerId, Carbon $date): array
    {
        $type = CommissionType::tryFrom((int) $event->pk_evento_comision_tipo);
        $storedEvents = config('boxoffice.rules.user_cut_stored_commission_events');
        $fixedOverride = config('boxoffice.rules.discounted_fixed_commission')[$event->pk_evento] ?? null;

        $commission = function ($row, PaymentType $paymentType) use ($event, $type, $storedEvents, $fixedOverride) {
            if ($paymentType === PaymentType::Terminal || in_array($event->pk_evento, $storedEvents, true)) {
                return (float) $row->total_comision;
            }

            if ((float) $row->precio === 0.0) {
                return 0.0;
            }

            return match ($type) {
                CommissionType::Percentage => ((int) $row->total_vendidos * (float) $row->precio) * ((float) $row->comision / 100),
                CommissionType::Fixed => (int) $row->total_vendidos * (($fixedOverride !== null && $row->porcentaje_diferencia > 0) ? $fixedOverride : (float) $row->comision),
                default => 0.0,
            };
        };

        $tables = $this->tablesByType($event, $sellerId, $date, $date, cashCut: false, commission: $commission);

        return $this->byFunction($event, fn (int $functionId) => $this->sections($tables, $functionId));
    }

    /**
     * Ventas del vendedor en un rango, con resumen general y venta web.
     */
    public function salesByDate(EventoVista $event, int $sellerId, Carbon $from, Carbon $to): array
    {
        $commission = fn ($row) => $this->legacyCommission($event, $row);
        $tables = $this->tablesByType($event, $sellerId, $from, $to, cashCut: false, commission: $commission);
        $tables[PaymentType::Web->value] = $this->rows(
            $this->query->byPaymentType($event->pk_evento, PaymentType::Web, null, $from, $to, false),
            PaymentType::Web,
            $commission,
        );

        $sold = $this->query->sold($event->pk_evento, $sellerId, $from, $to);
        $courtesies = $this->query->courtesies($event->pk_evento, $from, $to);
        $localityNames = EventosLocalidades::query()
            ->where('pk_evento', $event->pk_evento)
            ->pluck('evento_localidad', 'pk_evento_localidad');

        return $this->byFunction($event, function (int $functionId) use ($tables, $sold, $courtesies, $localityNames) {
            return [
                'general' => $this->generalTable($functionId, $sold, $courtesies, $localityNames),
            ] + $this->sections($tables, $functionId, [PaymentType::Cash, PaymentType::Terminal, PaymentType::Transfer, PaymentType::Web]);
        });
    }

    /**
     * Comisión de ventas por fecha: a partir del evento configurado se usa la
     * guardada en el boleto; antes se recalcula con el tipo de comisión.
     */
    private function legacyCommission(EventoVista $event, object $row): float
    {
        if ($event->pk_evento >= (int) config('boxoffice.rules.stored_commission_since_event')) {
            return (float) $row->total_comision;
        }

        $tickets = (int) $row->total_vendidos * (float) $row->precio;

        if ((int) $event->pk_evento_comision_tipo === CommissionType::Percentage->value) {
            return $tickets * ((float) $row->comision / 100);
        }

        $commission = (float) $row->comision;

        if ($row->porcentaje_diferencia > 0) {
            $commission -= $commission * $row->porcentaje_diferencia / 100;
        }

        return (int) $row->total_vendidos * $commission;
    }

    /**
     * @return array<int, Collection>
     */
    private function tablesByType(EventoVista $event, int $sellerId, Carbon $from, Carbon $to, bool $cashCut, callable $commission): array
    {
        $tables = [];

        foreach (self::BOX_OFFICE_TYPES as $type) {
            $tables[$type->value] = $this->rows(
                $this->query->byPaymentType($event->pk_evento, $type, $sellerId, $from, $to, $cashCut),
                $type,
                $commission,
            );
        }

        return $tables;
    }

    private function rows(Collection $rows, PaymentType $type, callable $commission): Collection
    {
        return $rows->map(function ($row) use ($type, $commission) {
            $tickets = (int) $row->total_vendidos * (float) $row->precio;
            $fee = (float) $commission($row, $type);
            $cardFee = $type === PaymentType::Terminal ? (float) $row->total_servicio : 0.0;

            return [
                'function_id' => (int) $row->pk_evento_funcion,
                'locality_id' => (int) $row->pk_evento_localidad,
                'locality' => $row->evento_localidad,
                'payment' => $type->label(),
                'quantity' => (int) $row->total_vendidos,
                'unit_price' => (float) $row->precio,
                'tickets' => $tickets,
                'commission' => $fee,
                'card_commission' => $cardFee,
                'total' => $tickets + $fee + $cardFee,
            ];
        });
    }

    /**
     * @param  array<int, Collection>  $tables
     * @param  list<PaymentType>|null  $types
     */
    private function sections(array $tables, int $functionId, ?array $types = null): array
    {
        $sections = [];

        foreach ($types ?? self::BOX_OFFICE_TYPES as $type) {
            $rows = ($tables[$type->value] ?? collect())->where('function_id', $functionId)->values();

            $sections[$type->name] = [
                'title' => $type->label(),
                'show_card_commission' => $type === PaymentType::Terminal,
                'rows' => $rows->all(),
                'totals' => [
                    'quantity' => $rows->sum('quantity'),
                    'tickets' => $rows->sum('tickets'),
                    'commission' => $rows->sum('commission'),
                    'card_commission' => $rows->sum('card_commission'),
                    'total' => $rows->sum('total'),
                ],
            ];
        }

        return $sections;
    }

    private function generalTable(int $functionId, Collection $sold, Collection $courtesies, Collection $localityNames): array
    {
        $rows = $sold->where('pk_evento_funcion', $functionId)->map(function ($row) use ($functionId, $courtesies, $localityNames) {
            $isListPrice = (float) $row->precio === (float) $row->precio_lista;

            return [
                'locality' => $localityNames->get((int) $row->pk_evento_localidad, (string) $row->pk_evento_localidad),
                'quantity' => (int) $row->total_boletos_vendidos,
                'courtesies' => $isListPrice ? (int) ($courtesies["{$functionId}:{$row->pk_evento_localidad}"] ?? 0) : 0,
                'unit_price' => (float) $row->precio,
                'total' => (int) $row->total_boletos_vendidos * (float) $row->precio,
            ];
        })->values();

        $soldLocalities = $sold->where('pk_evento_funcion', $functionId)->pluck('pk_evento_localidad')->map(fn ($id) => (int) $id);

        foreach ($courtesies as $bucket => $count) {
            [$bucketFunction, $localityId] = array_map('intval', explode(':', $bucket));

            if ($bucketFunction === $functionId && ! $soldLocalities->contains($localityId)) {
                $rows->push([
                    'locality' => $localityNames->get($localityId, (string) $localityId),
                    'quantity' => 0,
                    'courtesies' => $count,
                    'unit_price' => 0.0,
                    'total' => 0.0,
                ]);
            }
        }

        return [
            'rows' => $rows->all(),
            'totals' => [
                'quantity' => $rows->sum('quantity'),
                'courtesies' => $rows->sum('courtesies'),
                'total' => $rows->sum('total'),
            ],
        ];
    }

    private function byFunction(EventoVista $event, callable $sections): array
    {
        return EventoFuncionVista::query()
            ->where('pk_evento', $event->pk_evento)
            ->orderBy('fecha')
            ->get()
            ->map(fn (EventoFuncionVista $function) => [
                'function' => $function,
                'sections' => $sections($function->pk_evento_funcion),
            ])
            ->all();
    }
}
