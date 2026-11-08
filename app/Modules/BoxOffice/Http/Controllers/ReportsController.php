<?php

namespace App\Modules\BoxOffice\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BoxOffice\Exports\RaceParticipantsExport;
use App\Modules\BoxOffice\Policies\BoxOfficeAbilities;
use App\Modules\BoxOffice\Queries\EventReportQuery;
use App\Modules\BoxOffice\Services\Reports\EventReportBuilder;
use App\Modules\BoxOffice\Support\SpanishDate;
use App\Modules\Events\Models\Eventos;
use App\Modules\Events\Models\Views\EventoFuncionVista;
use App\Modules\Events\Models\Views\EventoVista;
use App\Modules\Events\Models\Views\UsuarioEventoVista;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ReportsController extends Controller
{
    /**
     * Tipo de reporte => privilegio requerido (null = cualquiera con acceso a reportes).
     */
    private const REPORTS = [
        'general' => null,
        'pagos' => 'payments_report',
        'whatsapp' => 'whatsapp_report',
        'detallado' => 'reports_admin',
        'ventas' => 'reports_admin',
        'estacionamiento' => 'reports_admin',
        'general-td' => 'reports_admin',
        'detallado-td' => 'reports_admin',
    ];

    public function __construct(private readonly EventReportBuilder $reports) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $search = trim((string) $request->query('evento', ''));

        $events = UsuarioEventoVista::query()
            ->delUsuario($user->pk_usuario)
            ->when($search !== '', fn ($query) => $query->where('evento', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->orderByDesc('inicio_fecha')
            ->limit(200)
            ->get(['pk_evento', 'evento', 'inicio_fecha_natural', 'escenario', 'ciudad', 'estado', 'estacionamiento']);

        return Inertia::render('BoxOffice/Reports/Index', [
            'search' => $search,
            'events' => $events->map(fn (UsuarioEventoVista $event) => [
                'id' => $event->pk_evento,
                'name' => $event->evento,
                'date' => $event->inicio_fecha_natural,
                'venue' => $event->escenario,
                'city' => $event->ciudad,
                'state' => $event->estado,
                'parking' => (int) ($event->estacionamiento ?? 0) > 0,
            ])->values(),
            'allowed' => collect(self::REPORTS)->filter(fn ($privilege) => $this->can($request, $privilege))->keys()->values(),
        ]);
    }

    public function show(Request $request, string $tipo, int $evento): HttpResponse
    {
        abort_unless(array_key_exists($tipo, self::REPORTS), 404);
        abort_unless($this->can($request, self::REPORTS[$tipo]), 403);

        $event = $this->event($evento);

        [$view, $title, $data] = match ($tipo) {
            'general' => ['general', 'Reporte general', ['functions' => $this->reports->general($event)]],
            'general-td' => ['general', 'Reporte general (Defender)', ['functions' => $this->reports->general($event, defenderOnly: true)]],
            'detallado' => ['detailed', 'Reporte detallado', ['functions' => $this->reports->detailed($event)]],
            'detallado-td' => ['detailed', 'Reporte detallado (Defender)', ['functions' => $this->reports->detailed($event, defenderOnly: true)]],
            'ventas' => ['detailed', 'Reporte de ventas', ['functions' => $this->reports->detailed($event, paidWebOnly: true)]],
            'pagos' => ['payments', 'Pagos del evento', $this->reports->payments($event)],
            'whatsapp' => ['whatsapp', 'Envíos por WhatsApp', ['functions' => $this->reports->whatsapp($event)]],
            'estacionamiento' => ['parking', 'Estacionamiento', $this->reports->parking($event)],
        };

        return $this->pdf("boxoffice.pdf.reports.{$view}", $event, $title, $data + [
            'pendingColumns' => in_array($event->pk_evento, config('boxoffice.rules.report_pending_columns_events'), true),
        ], "{$tipo}-{$evento}");
    }

    public function daily(Request $request): Response
    {
        return Inertia::render('BoxOffice/Reports/Daily', [
            'events' => $this->eventOptions($request),
            'today' => now(config('boxoffice.timezone'))->toDateString(),
        ]);
    }

    public function dailyPdf(Request $request): HttpResponse
    {
        $validated = $request->validate([
            'evento' => ['required', 'integer'],
            'fecha' => ['required', 'date_format:Y-m-d'],
        ]);

        $event = $this->event((int) $validated['evento']);
        $day = Carbon::createFromFormat('Y-m-d', $validated['fecha'], config('boxoffice.timezone'))->startOfDay();

        return $this->pdf('boxoffice.pdf.reports.daily', $event, 'Ventas del día', $this->reports->daily($event, $day), "ventas-dia-{$event->pk_evento}", SpanishDate::long($day));
    }

    public function entries(Request $request): Response
    {
        return Inertia::render('BoxOffice/Reports/Entries', [
            'events' => $request->user()->eventos()
                ->publicados()
                ->with('escenario:pk_escenario,escenario')
                ->orderByDesc('inicio_fecha')
                ->get(['eventos.pk_evento', 'eventos.pk_escenario', 'eventos.evento'])
                ->map(fn (Eventos $event) => ['id' => $event->pk_evento, 'name' => $event->evento, 'venue' => $event->escenario?->escenario])
                ->values(),
        ]);
    }

    public function entryFunctions(int $evento): JsonResponse
    {
        Gate::authorize(BoxOfficeAbilities::EVENT, $evento);

        return response()->json(
            EventoFuncionVista::query()->where('pk_evento', $evento)->orderBy('fecha')->get()
                ->map(fn (EventoFuncionVista $function) => [
                    'id' => $function->pk_evento_funcion,
                    'label' => "{$function->fecha_natural} {$function->hora_natural} h",
                ])->values(),
        );
    }

    public function entryTotals(EventReportQuery $query, int $evento, int $funcion): JsonResponse
    {
        Gate::authorize(BoxOfficeAbilities::EVENT, $evento);

        $entries = $query->entries($evento, $funcion)->map(fn ($row) => ['locality' => $row->locality, 'total' => (int) $row->total]);

        return response()->json(['localities' => $entries->values(), 'total' => $entries->sum('total')]);
    }

    public function participants(Request $request): Response
    {
        return Inertia::render('BoxOffice/Reports/Participants', [
            'events' => $this->eventOptions($request, fn ($query) => $query->where('pk_evento_categoria', Eventos::CATEGORY_RACE)),
            'today' => now(config('boxoffice.timezone'))->toDateString(),
        ]);
    }

    public function participantsExport(Request $request, EventReportQuery $query): BinaryFileResponse
    {
        $validated = $request->validate([
            'evento' => ['required', 'integer'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ]);

        $event = $this->event((int) $validated['evento']);
        $tz = config('boxoffice.timezone');
        $data = $query->raceParticipants(
            $event->pk_evento,
            isset($validated['desde']) ? Carbon::createFromFormat('Y-m-d', $validated['desde'], $tz) : null,
            isset($validated['hasta']) ? Carbon::createFromFormat('Y-m-d', $validated['hasta'], $tz) : null,
        );

        return Excel::download(new RaceParticipantsExport($event->pk_evento, $data['registered'], $data['pending']), "participantes-{$event->pk_evento}.xlsx");
    }

    private function eventOptions(Request $request, ?callable $scope = null): array
    {
        return UsuarioEventoVista::query()
            ->delUsuario($request->user()->pk_usuario)
            ->when($scope, $scope)
            ->orderByDesc('inicio_fecha')
            ->get(['pk_evento', 'evento', 'inicio_fecha_natural'])
            ->map(fn (UsuarioEventoVista $event) => ['id' => $event->pk_evento, 'name' => $event->evento, 'date' => $event->inicio_fecha_natural])
            ->values()
            ->all();
    }

    private function can(Request $request, ?string $privilege): bool
    {
        return $privilege === null || $request->user()->hasPrivilege((int) config("boxoffice.privileges.{$privilege}"));
    }

    private function event(int $eventId): EventoVista
    {
        Gate::authorize(BoxOfficeAbilities::EVENT, $eventId);

        return EventoVista::query()->findOrFail($eventId);
    }

    private function pdf(string $view, EventoVista $event, string $title, array $data, string $filename, ?string $period = null): HttpResponse
    {
        return Pdf::loadView($view, $data + [
            'title' => $title,
            'event' => $event,
            'period' => $period ?? SpanishDate::long(now(config('boxoffice.timezone'))),
            'generatedAt' => Carbon::now(config('boxoffice.timezone')),
        ])->setPaper('letter', 'landscape')->stream($filename.'.pdf');
    }
}
