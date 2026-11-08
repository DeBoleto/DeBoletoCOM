<?php

namespace App\Modules\BoxOffice\Services\Reports;

use App\Modules\BoxOffice\Queries\EventReportQuery;
use App\Modules\BoxOffice\Queries\SalesBreakdownQuery;
use App\Modules\Events\Enums\CommissionType;
use App\Modules\Events\Models\EventoLocalidadAsiento;
use App\Modules\Events\Models\EventosLocalidades;
use App\Modules\Events\Models\Views\EventoFuncionVista;
use App\Modules\Events\Models\Views\EventoVista;
use App\Modules\Orders\Enums\PaymentType;
use App\Modules\Orders\Models\EventosComprasTipos;
use App\Modules\Tickets\Enums\TicketStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Datos de los reportes PDF por evento (general, detallado, pagos, WhatsApp,
 * estacionamiento y ventas del día).
 */
class EventReportBuilder
{
    public function __construct(
        private readonly EventReportQuery $query,
        private readonly SalesBreakdownQuery $breakdown,
    ) {}

    /**
     * Reporte general: aforo, vendidos, cortesías, reservas y por vender.
     */
    public function general(EventoVista $event, bool $defenderOnly = false): array
    {
        $aggregates = $this->query->ticketAggregates($event->pk_evento, $defenderOnly);
        $localities = $this->localities($event);
        $tables = $this->tableLocalities($event);
        $pendingPrices = in_array($event->pk_evento, config('boxoffice.rules.report_pending_prices_events'), true);
        $valuedCourtesyFrom = config('boxoffice.rules.courtesy_valued_localities')[$event->pk_evento] ?? null;

        return $this->openFunctions($event)->map(function (EventoFuncionVista $function) use ($aggregates, $localities, $tables, $pendingPrices, $valuedCourtesyFrom) {
            $rows = $aggregates->where('pk_evento_funcion', $function->pk_evento_funcion);
            $lines = [];
            $hasCapacity = true;
            $reserved = 0;

            foreach ($localities as $locality) {
                $mine = $rows->where('pk_evento_localidad', $locality->pk_evento_localidad);
                $count = fn (callable $filter) => (int) $mine->filter($filter)->sum('total');
                $courtesies = $count(fn ($row) => $row->cortesia === 1 && $row->pk_evento_funcion_boleto_status !== TicketStatus::Blocked->value);
                $blocked = $count(fn ($row) => $row->cortesia === 1 && $row->pk_evento_funcion_boleto_status === TicketStatus::Blocked->value);
                $reserved += $blocked;
                $capacity = (int) ($locality->capacidad ?? 0);
                $isTable = $tables->has($locality->pk_evento_localidad);

                $otherPriceStatus = $pendingPrices ? TicketStatus::Pending->value : TicketStatus::Issued->value;
                $otherPrices = $mine
                    ->filter(fn ($row) => $row->cortesia === 0 && $row->pk_evento_funcion_boleto_status === $otherPriceStatus && $row->precio != $locality->precio)
                    ->pluck('precio')
                    ->unique();

                $paid = fn (float $price, int $status) => $count(fn ($row) => $row->cortesia === 0 && $row->pk_evento_funcion_boleto_status === $status && $row->precio == $price);

                if ($isTable) {
                    $seatPrice = $locality->precio / max(1, $tables->get($locality->pk_evento_localidad));
                    $soldAmount = (float) $mine->filter(fn ($row) => $row->cortesia === 0 && $row->pk_evento_funcion_boleto_status === TicketStatus::Issued->value)->sum('amount');

                    foreach ($otherPrices as $price) {
                        $sold = $paid($price, TicketStatus::Issued->value);
                        $toSell = $capacity - $sold;
                        $lines[] = $this->generalLine($locality, $price, $capacity, $sold, $courtesies, $blocked, $toSell, $toSell * $seatPrice, -$sold, $soldAmount, $toSell * $seatPrice, $sold + $blocked + $toSell);
                    }

                    continue;
                }

                $sold = $paid($locality->precio, TicketStatus::Issued->value);
                $pending = $paid($locality->precio, TicketStatus::Pending->value);
                $total = $sold + $courtesies + $blocked + $pending;
                $available = $capacity - $total;
                $saleAmount = $sold * $locality->precio;

                if ($valuedCourtesyFrom !== null && $locality->pk_evento_localidad >= $valuedCourtesyFrom) {
                    $saleAmount += $courtesies * 1.0;
                }

                if ($locality->capacidad === null) {
                    $hasCapacity = false;
                }

                $lines[] = $this->generalLine(
                    $locality, $locality->precio, $capacity, $sold, $courtesies, $blocked, $pending,
                    $pending * $locality->precio, $available, $saleAmount, ($available * $locality->precio) + ($pending * $locality->precio), $total,
                );

                foreach ($otherPrices as $price) {
                    $soldAtPrice = $paid($price, TicketStatus::Issued->value);
                    $pendingAtPrice = $paid($price, TicketStatus::Pending->value);
                    $lines[] = $this->generalLine(
                        $locality, $price, 0, $soldAtPrice, 0, 0, $pendingAtPrice, $pendingAtPrice * $price,
                        -$soldAtPrice, $soldAtPrice * $price, $pendingAtPrice * $price, $soldAtPrice,
                    );
                }
            }

            $soldTotal = (int) $rows->filter(fn ($row) => $row->cortesia === 0 && $row->pk_evento_funcion_boleto_status === TicketStatus::Issued->value)->sum('total');
            $courtesyTotal = (int) $rows->filter(fn ($row) => $row->cortesia === 1 && $row->pk_evento_funcion_boleto_status !== TicketStatus::Blocked->value)->sum('total');

            return [
                'function' => $function,
                'lines' => $lines,
                'sold' => $soldTotal,
                'courtesies' => $courtesyTotal,
                'reserved' => $reserved,
                'people' => $soldTotal + $courtesyTotal,
                'hasCapacity' => $hasCapacity,
                'totals' => $this->sumColumns($lines, ['capacity', 'sold', 'courtesies', 'blocked', 'toSell', 'toCollect', 'available', 'saleAmount', 'pendingAmount', 'total']),
            ];
        })->all();
    }

    /**
     * Reporte detallado: resumen por localidad y tablas por forma de pago.
     *
     * @param  bool  $paidWebOnly  Variante "ventas": excluye boletos web sin precio.
     */
    public function detailed(EventoVista $event, bool $defenderOnly = false, bool $paidWebOnly = false): array
    {
        $aggregates = $this->query->ticketAggregates($event->pk_evento, $defenderOnly);
        $localities = $this->localities($event)->keyBy('pk_evento_localidad');
        $tables = $this->tableLocalities($event);
        $webRate = (float) EventosComprasTipos::query()->whereKey(PaymentType::Web->value)->value('comision_porcentaje');
        $valuedCourtesyFrom = config('boxoffice.rules.courtesy_valued_localities')[$event->pk_evento] ?? null;

        $byType = [];
        foreach ([PaymentType::Cash, PaymentType::Terminal, PaymentType::Transfer, PaymentType::Web] as $type) {
            $byType[$type->value] = $this->breakdown->byPaymentType(
                $event->pk_evento, $type, null, null, null, false,
                paidOnly: $paidWebOnly && $type === PaymentType::Web,
                defenderOnly: $defenderOnly,
            );
        }

        return $this->openFunctions($event)->map(function (EventoFuncionVista $function) use ($event, $aggregates, $localities, $tables, $webRate, $byType, $valuedCourtesyFrom) {
            $rows = $aggregates->where('pk_evento_funcion', $function->pk_evento_funcion);
            $general = [];

            foreach ($localities as $locality) {
                $mine = $rows->where('pk_evento_localidad', $locality->pk_evento_localidad);
                $sold = $mine->filter(fn ($row) => $row->cortesia === 0 && $row->pk_evento_funcion_boleto_status === TicketStatus::Issued->value)
                    ->groupBy(fn ($row) => (string) $row->precio);
                $courtesies = (int) $mine->filter(fn ($row) => $row->cortesia === 1 && $row->pk_evento_funcion_boleto_status !== TicketStatus::Blocked->value)->sum('total');
                $blocked = (int) $mine->filter(fn ($row) => $row->cortesia === 1 && $row->pk_evento_funcion_boleto_status === TicketStatus::Blocked->value)->sum('total');
                $isTable = $tables->has($locality->pk_evento_localidad);

                foreach ($sold as $price => $group) {
                    $price = (float) $price;
                    $quantity = (int) $group->sum('total');
                    $discount = $locality->precio > 0 ? round((($locality->precio - $price) / $locality->precio) * 100, 2) : 0.0;
                    $service = $this->serviceCommission($event, $locality, $price, $quantity, $discount, (float) $group->sum('service_fee'), $isTable);
                    $online = $this->onlineCommission($event, $locality, $price, (int) $group->sum('web_tickets'), (int) $group->sum('web_purchases'), (float) $group->sum('payment_fee'), $webRate);
                    $lineCourtesies = ($price == $locality->precio || $sold->count() === 1) ? $courtesies : 0;
                    $amount = $quantity * $price;

                    if ($valuedCourtesyFrom !== null && $locality->pk_evento_localidad >= $valuedCourtesyFrom) {
                        $amount += $lineCourtesies * 1.0;
                    }

                    $general[] = [
                        'locality' => $locality->evento_localidad,
                        'quantity' => $quantity,
                        'courtesies' => $lineCourtesies,
                        'blocked' => $blocked,
                        'unit_price' => $price,
                        'service' => $service,
                        'online' => $online,
                        'amount' => $amount,
                        'commissions' => $service + $online,
                        'total' => $amount + $service + $online,
                    ];
                }
            }

            $payments = [];
            foreach ($byType as $typeValue => $typeRows) {
                $type = PaymentType::from($typeValue);
                $payments[$type->name] = $this->paymentTable($event, $type, $typeRows->where('pk_evento_funcion', $function->pk_evento_funcion), $localities, $tables);
            }

            return [
                'function' => $function,
                'general' => [
                    'rows' => $general,
                    'totals' => $this->sumColumns($general, ['quantity', 'courtesies', 'service', 'online', 'amount', 'commissions', 'total']),
                ],
                'payments' => $payments,
            ];
        })->all();
    }

    public function payments(EventoVista $event): array
    {
        $payments = $this->query->payments($event->pk_evento);

        return ['rows' => $payments->all(), 'total' => $payments->sum('amount')];
    }

    public function whatsapp(EventoVista $event): array
    {
        $rows = $this->query->whatsappSales($event->pk_evento);
        $fee = (float) config('boxoffice.whatsapp_fee', 15);

        return $this->openFunctions($event)->map(function (EventoFuncionVista $function) use ($rows, $fee) {
            $mine = $rows->where('pk_evento_funcion', $function->pk_evento_funcion)->map(fn ($row) => [
                'locality' => $row->evento_localidad,
                'purchases' => (int) $row->purchases,
                'tickets' => (int) $row->tickets,
                'cost' => (int) $row->purchases * $fee,
            ])->values();

            return [
                'function' => $function,
                'rows' => $mine->all(),
                'totals' => $this->sumColumns($mine->all(), ['purchases', 'tickets', 'cost']),
            ];
        })->all();
    }

    public function parking(EventoVista $event): array
    {
        $rows = $this->query->parking($event->pk_evento)->map(fn ($row) => ['locality' => $row->evento_localidad, 'total' => (int) $row->total]);

        return ['rows' => $rows->all(), 'total' => $this->query->parkingTotal($event->pk_evento)];
    }

    /**
     * Ventas del día por forma de pago y totales por localidad.
     */
    public function daily(EventoVista $event, Carbon $day): array
    {
        $percentage = (int) $event->pk_evento_comision_tipo === CommissionType::Percentage->value;
        $rows = $this->query->dailySales($event->pk_evento, $day, $percentage);

        $byType = collect(PaymentType::cases())
            ->reject(fn (PaymentType $type) => $type === PaymentType::Courtesy)
            ->map(function (PaymentType $type) use ($rows) {
                $mine = $rows->where('pk_evento_compra_tipo', $type->value)->map(fn ($row) => [
                    'purchase' => (int) $row->pk_evento_compra,
                    'time' => Carbon::parse($row->creacion_fecha)->format('H:i'),
                    'seat' => str_starts_with((string) $row->asiento, 'NA_NA') ? null : $row->asiento,
                    'locality' => $row->evento_localidad,
                    'tickets' => (int) $row->tickets,
                    'amount' => (float) $row->amount,
                    'commission' => (float) $row->commission,
                ])->values();

                return [
                    'title' => $type->label(),
                    'rows' => $mine->all(),
                    'totals' => $this->sumColumns($mine->all(), ['tickets', 'amount', 'commission']),
                ];
            })
            ->filter(fn (array $section) => $section['rows'] !== [])
            ->values();

        $byLocality = $rows->groupBy('evento_localidad')->map(fn (Collection $group, string $locality) => [
            'locality' => $locality,
            'tickets' => (int) $group->sum('tickets'),
            'amount' => (float) $group->sum('amount'),
            'commission' => (float) $group->sum('commission'),
        ])->values();

        return [
            'sections' => $byType->all(),
            'localities' => $byLocality->all(),
            'totals' => $this->sumColumns($byLocality->all(), ['tickets', 'amount', 'commission']),
        ];
    }

    /**
     * Tabla de una forma de pago con la comisión según la regla legacy.
     */
    private function paymentTable(EventoVista $event, PaymentType $type, Collection $rows, Collection $localities, Collection $tables): array
    {
        $lines = $rows->map(function ($row) use ($event, $type, $localities, $tables) {
            $locality = $localities->get((int) $row->pk_evento_localidad);
            $price = (float) $row->precio;
            $quantity = (int) $row->total_vendidos;
            $service = $price > 0 && $locality
                ? $this->serviceCommission($event, $locality, $price, $quantity, (float) $row->porcentaje_diferencia, (float) $row->total_comision, $tables->has($locality->pk_evento_localidad))
                : 0.0;
            $card = $type === PaymentType::Terminal || $type === PaymentType::Web
                ? (float) $row->total_servicio
                : 0.0;

            return [
                'locality' => $row->evento_localidad,
                'quantity' => $quantity,
                'unit_price' => $price,
                'amount' => $quantity * $price,
                'service' => $service,
                'card' => $card,
                'total' => ($quantity * $price) + $service + $card,
            ];
        })->values()->all();

        return [
            'title' => $type->label(),
            'show_card' => in_array($type, [PaymentType::Terminal, PaymentType::Web], true),
            'card_label' => $type === PaymentType::Web ? 'Pago en línea' : 'Terminal',
            'rows' => $lines,
            'totals' => $this->sumColumns($lines, ['quantity', 'amount', 'service', 'card', 'total']),
        ];
    }

    /**
     * Comisión DeBoleto: guardada en boletos desde el evento configurado;
     * antes, recalculada por tipo y reducida por el % de descuento.
     */
    private function serviceCommission(EventoVista $event, EventosLocalidades $locality, float $price, int $quantity, float $discountPercent, float $stored, bool $isTable): float
    {
        if ($event->pk_evento >= (int) config('boxoffice.rules.stored_commission_since_event')) {
            return $stored;
        }

        $type = CommissionType::tryFrom((int) $event->pk_evento_comision_tipo);
        $commission = match ($type) {
            CommissionType::Percentage, CommissionType::PercentageNoCardFee => $price * ($locality->comision / 100) * $quantity,
            CommissionType::Fixed, CommissionType::FixedNoCardFee => $locality->comision * $quantity,
            default => 0.0,
        };

        $exempt = config('boxoffice.rules.commission_discount_exempt');
        $tableExempt = $isTable && in_array($type, [CommissionType::Percentage, CommissionType::Fixed], true);

        if ($discountPercent > 0 && ! $tableExempt
            && ! in_array($event->pk_evento, $exempt['events'], true)
            && ! in_array($event->pk_evento_categoria, $exempt['categories'], true)) {
            $commission -= $commission * $discountPercent / 100;
        }

        return $commission;
    }

    private function onlineCommission(EventoVista $event, EventosLocalidades $locality, float $price, int $webTickets, int $webPurchases, float $stored, float $webRate): float
    {
        if ($event->pk_evento >= (int) config('boxoffice.rules.stored_commission_since_event')) {
            return $stored;
        }

        if ($webTickets === 0) {
            return 0.0;
        }

        return (($price + $locality->comision) * ($webRate / 100) * $webTickets)
            + ($webPurchases * (float) config('boxoffice.gateway_fixed_fee', 2.5));
    }

    private function generalLine(EventosLocalidades $locality, float $price, int $capacity, int $sold, int $courtesies, int $blocked, int $toSell, float $toCollect, int $available, float $saleAmount, float $pendingAmount, int $total): array
    {
        return [
            'locality' => $locality->evento_localidad,
            'price' => $price,
            'capacity' => $capacity,
            'sold' => $sold,
            'courtesies' => $courtesies,
            'blocked' => $blocked,
            'toSell' => $toSell,
            'toCollect' => $toCollect,
            'available' => $available,
            'saleAmount' => $saleAmount,
            'pendingAmount' => $pendingAmount,
            'total' => $total,
            'percentage' => $capacity > 0 ? round(($sold + $courtesies) / $capacity * 100, 1) : null,
        ];
    }

    private function sumColumns(array $rows, array $columns): array
    {
        return collect($columns)->mapWithKeys(fn (string $column) => [$column => collect($rows)->sum($column)])->all();
    }

    private function openFunctions(EventoVista $event): Collection
    {
        return EventoFuncionVista::query()
            ->where('pk_evento', $event->pk_evento)
            ->where('cerrado', 0)
            ->orderBy('fecha')
            ->get();
    }

    private function localities(EventoVista $event): Collection
    {
        return EventosLocalidades::query()->where('pk_evento', $event->pk_evento)->orderBy('pk_evento_localidad')->get();
    }

    /**
     * @return Collection<int, int> pk_evento_localidad => asientos por mesa
     */
    private function tableLocalities(EventoVista $event): Collection
    {
        return EventoLocalidadAsiento::query()
            ->whereIn('pk_evento_localidad', EventosLocalidades::query()->where('pk_evento', $event->pk_evento)->select('pk_evento_localidad'))
            ->pluck('cantidad_asientos', 'pk_evento_localidad')
            ->map(fn ($seats) => (int) $seats);
    }
}
