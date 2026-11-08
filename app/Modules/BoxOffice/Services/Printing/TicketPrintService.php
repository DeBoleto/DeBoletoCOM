<?php

namespace App\Modules\BoxOffice\Services\Printing;

use App\Modules\Events\Models\Views\EventoVista;
use App\Modules\Orders\Models\EventosCompras;
use App\Modules\Shared\Models\LegacyModel;
use App\Modules\Tickets\Enums\TicketStatus;
use App\Modules\Tickets\Models\BoletosFormatoTipo;
use App\Modules\Tickets\Models\EventosFuncionesBoletos;
use App\Modules\Users\Models\Usuarios;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class TicketPrintService
{
    public function __construct(private readonly TicketPresenter $presenter) {}

    /**
     * Página de impresión física de una compra. Marca los boletos como impresos.
     */
    public function printView(EventosCompras $purchase, Usuarios $user, string $strategy = TicketPresenter::STORED, ?int $formatId = null): View
    {
        $data = $this->presenter->forPurchase($purchase, $strategy);
        $this->markDelivered($purchase->boletos(), $user, printed: true);

        return view('boxoffice.print.formats.'.$this->formatFor($data['evento'], $user, false, $formatId), $data);
    }

    /**
     * Impresión de boletos sueltos (cortesías).
     *
     * @param  Collection<int, EventosFuncionesBoletos>  $tickets
     */
    public function printCourtesies(Collection $tickets, Usuarios $user, ?int $formatId = null): View
    {
        $data = $this->presenter->forTickets($tickets);
        $this->markDelivered(EventosFuncionesBoletos::query()->whereKey($tickets->modelKeys()), $user, printed: true);

        return view('boxoffice.print.formats.'.$this->formatFor($data['evento'], $user, true, $formatId), $data);
    }

    /**
     * Boleto digital en PDF. Marca los boletos como entregados.
     */
    public function digitalPdf(EventosCompras $purchase, Usuarios $user, string $strategy = TicketPresenter::DIGITAL, bool $printed = false): Response
    {
        $document = $this->digitalDocument($purchase, $strategy);
        $this->markDelivered($purchase->boletos(), $user, $printed);

        return $document->download('compra-'.$purchase->pk_evento_compra.'.pdf');
    }

    public function digitalDocument(EventosCompras $purchase, string $strategy = TicketPresenter::DIGITAL): DomPdf
    {
        return Pdf::loadView('boxoffice.pdf.digital-ticket', $this->presenter->forPurchase($purchase, $strategy))
            ->setPaper('letter');
    }

    public function markDelivered(Builder|HasMany $tickets, Usuarios $user, bool $printed): void
    {
        $tickets->update([
            'impresion' => $printed ? 1 : 0,
            'pk_evento_funcion_boleto_status' => TicketStatus::Issued->value,
            'modificacion_pk_usuario' => $user->pk_usuario,
            'modificacion_fecha' => Carbon::now(LegacyModel::legacyTimezone()),
        ]);
    }

    /**
     * Prioridad: formato elegido del catálogo > formato del usuario > formato del evento.
     */
    public function formatFor(EventoVista $event, Usuarios $user, bool $courtesy = false, ?int $catalogFormatId = null): string
    {
        if ($catalogFormatId !== null && ($view = $this->catalogView($catalogFormatId))) {
            return $view;
        }

        $formatId = $user->pk_formato_boleto ?? $event->pk_boleto_formato_tipo;
        $formats = $courtesy
            ? config('boxoffice.courtesy_print_formats') + config('boxoffice.print_formats')
            : config('boxoffice.print_formats');

        return $formats[$formatId] ?? config('boxoffice.default_print_format');
    }

    /**
     * @return array<int, string> id => etiqueta, solo formatos con vista conocida.
     */
    public function catalog(): array
    {
        return BoletosFormatoTipo::query()
            ->orderBy('pk_boleto_formato_tipo')
            ->get()
            ->filter(fn (BoletosFormatoTipo $format) => $this->viewFromLegacy((string) $format->vista) !== null)
            ->mapWithKeys(fn (BoletosFormatoTipo $format) => [
                $format->pk_boleto_formato_tipo => $format->boleto_formato_tipo ?? class_basename(str_replace('.', '\\', (string) $format->vista)),
            ])
            ->all();
    }

    private function catalogView(int $formatId): ?string
    {
        $legacy = BoletosFormatoTipo::query()->whereKey($formatId)->value('vista');

        return $legacy ? $this->viewFromLegacy($legacy) : null;
    }

    private function viewFromLegacy(string $legacyView): ?string
    {
        $basename = str_contains($legacyView, '.') ? substr($legacyView, strrpos($legacyView, '.') + 1) : $legacyView;

        return config('boxoffice.legacy_format_views')[$basename] ?? null;
    }
}
