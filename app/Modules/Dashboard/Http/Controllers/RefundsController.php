<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Dashboard\Exports\RowsExport;
use App\Modules\Events\Models\Views\EventoVista;
use App\Modules\Orders\Enums\PaymentType;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Reembolsos de eventos cancelados y compras con Ticket Defender.
 */
class RefundsController extends Controller
{
    private const REFUNDED_STATUS = 3;

    private const BANKS = [1 => 'BBVA Bancomer', 2 => 'HSBC', 3 => 'ScotiaBank', 4 => 'Santander', 5 => 'Banamex', 6 => 'Otros'];

    public function index(int $evento): Response
    {
        $event = EventoVista::query()->findOrFail($evento);
        $purchases = $this->purchases($evento);
        $sum = fn (?callable $filter = null) => $purchases->when($filter, fn ($items) => $items->filter($filter))->sum(fn ($row) => $row['subtotal'] + $row['commission']);

        return Inertia::render('Admin/Refunds/Index', [
            'event' => ['id' => $event->pk_evento, 'name' => $event->evento],
            'totals' => [
                'sold' => $sum(),
                'requested' => $sum(fn ($row) => $row['requested']),
                'refunded' => $sum(fn ($row) => $row['refunded']),
            ],
            'purchases' => $purchases->values(),
        ]);
    }

    public function export(int $evento): BinaryFileResponse
    {
        $details = DB::table('reembolso_compra')
            ->whereIn('pk_evento_compra', DB::table('eventos_compras')->where('pk_evento', $evento)->select('pk_evento_compra'))
            ->get()
            ->keyBy('pk_evento_compra');

        $rows = $this->purchases($evento)->map(function (array $row) use ($details) {
            $detail = $details->get($row['id']);

            return [
                'Folio de compra' => $row['id'],
                'Estatus' => $row['status'],
                'Forma de pago' => $row['payment'],
                'Boletos' => $row['tickets'],
                'Subtotal' => $row['subtotal'],
                'Comisión' => $row['commission'],
                'Total' => $row['subtotal'] + $row['commission'],
                'Correo' => $row['email'],
                'Titular' => $detail->titular_tarjeta ?? '',
                'Banco' => isset($detail->banco) ? (self::BANKS[(int) $detail->banco] ?? '') : '',
                'Cuenta' => $detail->no_cuenta ?? '',
                'CLABE' => $detail->clabe ?? '',
                'Tarjeta' => $detail->tarjeta ?? '',
                'Teléfono' => $detail->telefono ?? '',
                'Solicitado' => $detail->creacion_fecha ?? '',
            ];
        })->values()->all();

        return Excel::download(new RowsExport($rows, ['Folio de compra']), "reembolsos-{$evento}.xlsx");
    }

    public function defenderEvents(Request $request): Response
    {
        return Inertia::render('Admin/Refunds/Defender', [
            'events' => EventoVista::query()->where('defender', 1)->orderByDesc('pk_evento')->limit(200)->get(['pk_evento', 'evento', 'escenario'])
                ->map(fn (EventoVista $event) => ['id' => $event->pk_evento, 'name' => $event->evento, 'venue' => $event->escenario])->values(),
            'selected' => $request->integer('evento') ?: null,
            'summary' => fn () => $request->integer('evento') ? $this->defenderSummary($request->integer('evento')) : null,
        ]);
    }

    private function defenderSummary(int $eventId): array
    {
        $insured = (float) DB::table('eventos_compras')->where('pk_evento', $eventId)->sum('total_defender');
        $sold = (float) DB::table('eventos_funciones_boletos')->where('pk_evento', $eventId)->whereIn('pk_evento_funcion_boleto_status', [2, 4])->sum('precio');

        return [
            'insured' => $insured,
            'sold' => $sold,
            'pending' => $insured - $sold,
            'purchases' => DB::table('eventos_compras')
                ->where('pk_evento', $eventId)
                ->where('pk_evento_compra_tipo', PaymentType::Web->value)
                ->where('total_defender', '>', 0)
                ->orderBy('pk_evento_compra')
                ->get(['pk_evento_compra', 'tarjeta_banco', 'total_compra', 'total_defender'])
                ->map(fn ($row) => [
                    'id' => (int) $row->pk_evento_compra,
                    'bank' => $row->tarjeta_banco,
                    'total' => (float) $row->total_compra,
                    'defender' => (float) $row->total_defender,
                ])->values(),
        ];
    }

    private function purchases(int $eventId): Collection
    {
        return DB::table('eventos_compras as c')
            ->join('eventos_funciones_boletos as b', 'b.pk_evento_compra', '=', 'c.pk_evento_compra')
            ->leftJoin('usuarios as u', 'u.pk_usuario', '=', 'c.pk_usuario')
            ->where('c.pk_evento', $eventId)
            ->groupBy('c.pk_evento_compra', 'c.estatus', 'c.pk_evento_compra_tipo', 'u.correo')
            ->select(['c.pk_evento_compra', 'c.estatus', 'c.pk_evento_compra_tipo', 'u.correo'])
            ->selectRaw('COUNT(b.pk_evento_funcion_boleto) as boletos, SUM(b.precio) as subtotal, SUM(b.comision_deboleto) as comision')
            ->selectSub(fn ($query) => $query->from('reembolso_compra as r')->whereColumn('r.pk_evento_compra', 'c.pk_evento_compra')->selectRaw('COUNT(*)'), 'solicitudes')
            ->orderBy('c.pk_evento_compra')
            ->get()
            ->map(function ($row) {
                $requested = (int) $row->solicitudes > 0;
                $refunded = $requested && (int) $row->estatus === self::REFUNDED_STATUS;

                return [
                    'id' => (int) $row->pk_evento_compra,
                    'status' => $refunded ? 'Reembolsada' : ($requested ? 'Solicitud en proceso' : 'Sin solicitud'),
                    'requested' => $requested,
                    'refunded' => $refunded,
                    'payment' => PaymentType::tryFrom((int) $row->pk_evento_compra_tipo)?->label() ?? 'Web',
                    'tickets' => (int) $row->boletos,
                    'subtotal' => (float) $row->subtotal,
                    'commission' => (float) $row->comision,
                    'email' => $row->correo,
                ];
            });
    }
}
