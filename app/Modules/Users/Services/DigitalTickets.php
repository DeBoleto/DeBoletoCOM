<?php

namespace App\Modules\Users\Services;

use App\Modules\BoxOffice\Support\Barcode;
use App\Modules\BoxOffice\Support\EventImage;
use App\Modules\Events\Models\EventosCategorias;
use App\Modules\Events\Models\Views\EventoFuncionVista;
use App\Modules\Events\Models\Views\EventoVista;
use App\Modules\Orders\Enums\PaymentType;
use App\Modules\Orders\Enums\PurchaseStatus;
use App\Modules\Orders\Models\EventosCompras;
use App\Modules\Tickets\Models\EventoBoletoCarrera;
use App\Modules\Tickets\Models\EventoEstacionamientoBoleto;
use App\Modules\Tickets\Models\EventosFuncionesBoletos;
use App\Modules\Users\Models\Usuarios;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Compras y boletos digitales del perfil del cliente, con las reglas de
 * deboleto: solo las compras pagadas de eventos activos muestran sus boletos;
 * el QR lleva el id del boleto, igual que los boletos impresos y el PDF.
 */
class DigitalTickets
{
    private const ACTIVE_EVENT = 2;

    private const PDF_CATEGORY = 9;

    /**
     * @return list<array<string, mixed>>
     */
    public function purchases(Usuarios $customer): array
    {
        $purchases = EventosCompras::query()
            ->where('pk_usuario', $customer->pk_usuario)
            ->where('pk_evento_compra_tipo', '!=', PaymentType::Courtesy->value)
            ->orderByDesc('creacion_fecha')
            ->limit((int) config('profile.purchases_limit'))
            ->get();

        if ($purchases->isEmpty()) {
            return [];
        }

        $events = EventoVista::query()->whereIn('pk_evento', $purchases->pluck('pk_evento')->unique())->get()->keyBy('pk_evento');
        $functions = EventoFuncionVista::query()->whereIn('pk_evento_funcion', $purchases->pluck('pk_evento_funcion')->unique())->get()->keyBy('pk_evento_funcion');
        $tickets = EventosFuncionesBoletos::query()
            ->visibles()
            ->whereIn('pk_evento_compra', $purchases->pluck('pk_evento_compra'))
            ->with('localidad:pk_evento_localidad,evento_localidad')
            ->orderBy('pk_evento_funcion_boleto')
            ->get(['pk_evento_funcion_boleto', 'pk_evento_compra', 'pk_evento_localidad', 'asiento'])
            ->groupBy('pk_evento_compra');

        return $purchases
            ->filter(fn (EventosCompras $purchase) => $events->has($purchase->pk_evento))
            ->map(function (EventosCompras $purchase) use ($events, $functions, $tickets) {
                $event = $events->get($purchase->pk_evento);
                $function = $functions->get($purchase->pk_evento_funcion);
                $own = $tickets->get($purchase->pk_evento_compra, collect());

                return [
                    'code' => $purchase->codigo,
                    'folio' => $purchase->pk_evento_compra,
                    'event' => $this->event($event, $function),
                    'purchasedAt' => $purchase->creacion_fecha ? Carbon::parse($purchase->creacion_fecha)->toIso8601String() : null,
                    'total' => (float) $purchase->total_compra,
                    'tickets' => $own->count(),
                    'localities' => $own->map(fn (EventosFuncionesBoletos $ticket) => $ticket->localidad?->evento_localidad)->filter()->unique()->values(),
                    'seats' => $own->reject(fn (EventosFuncionesBoletos $ticket) => $ticket->isGeneralAdmission())->pluck('asiento')->take(15)->values(),
                    'action' => $this->action($purchase, $event),
                ];
            })
            ->values()
            ->all();
    }

    public function ownedBy(Usuarios $customer, string $code): ?EventosCompras
    {
        return EventosCompras::query()->where('codigo', $code)->where('pk_usuario', $customer->pk_usuario)->first();
    }

    public function byCode(string $code): ?EventosCompras
    {
        return EventosCompras::query()->where('codigo', $code)->first();
    }

    /**
     * Motivo por el que la compra no muestra boletos, o null si los muestra.
     */
    public function unavailable(EventosCompras $purchase): ?string
    {
        if ($purchase->status() !== PurchaseStatus::Paid) {
            return 'Esta compra no tiene boletos disponibles.';
        }

        $event = EventoVista::query()->find($purchase->pk_evento);

        if ($event === null || (int) $event->pk_evento_status !== self::ACTIVE_EVENT) {
            return 'El evento de esta compra ya terminó.';
        }

        return null;
    }

    /**
     * @return array{event: array<string, mixed>, purchase: array<string, mixed>, tickets: list<array<string, mixed>>, sponsors: list<array<string, mixed>>}
     */
    public function pass(EventosCompras $purchase): array
    {
        $event = EventoVista::query()->findOrFail($purchase->pk_evento);
        $function = EventoFuncionVista::query()->find($purchase->pk_evento_funcion);
        $race = EventosCategorias::isRace($event->pk_evento_categoria);

        $tickets = $this->tickets($purchase);
        $runners = $race
            ? EventoBoletoCarrera::query()->where('pk_evento_compra', $purchase->pk_evento_compra)->get()->keyBy('pk_evento_funcion_boleto')
            : collect();

        $passes = $tickets->map(fn (EventosFuncionesBoletos $ticket, int $index) => [
            'id' => $ticket->pk_evento_funcion_boleto,
            'kind' => 'ticket',
            'number' => $index + 1,
            'locality' => $ticket->localidad?->evento_localidad,
            'special' => $ticket->localidadEspecial?->evento_localidad,
            'seat' => $this->seat($ticket),
            'courtesy' => (bool) $ticket->cortesia,
            'scanned' => (bool) $ticket->getAttribute('escaneado'),
            'qr' => Barcode::qrDataUri($ticket->pk_evento_funcion_boleto, 320),
            'runner' => $this->runner($runners->get($ticket->pk_evento_funcion_boleto)),
        ]);

        $parking = EventoEstacionamientoBoleto::query()->where('pk_evento_compra', $purchase->pk_evento_compra)->get()
            ->map(fn (EventoEstacionamientoBoleto $pass, int $index) => [
                'id' => $tickets->first()?->pk_evento_funcion_boleto,
                'kind' => 'parking',
                'number' => $passes->count() + $index + 1,
                'locality' => 'Estacionamiento',
                'special' => $pass->lugar ?: null,
                'seat' => null,
                'courtesy' => false,
                'scanned' => (bool) $pass->getAttribute('asistencia'),
                'qr' => $tickets->isEmpty() ? null : Barcode::qrDataUri($tickets->first()->pk_evento_funcion_boleto, 320),
                'runner' => null,
            ]);

        return [
            'event' => $this->event($event, $function) + [
                'ticketImage' => EventImage::url($event->imagen_boleto) ?? EventImage::url($event->imagen),
                'isRace' => $race,
            ],
            'purchase' => [
                'code' => $purchase->codigo,
                'folio' => $purchase->pk_evento_compra,
                'holder' => $purchase->usuario?->nombreCompleto(),
            ],
            'tickets' => $passes->concat($parking)->values()->all(),
            'sponsors' => $this->sponsors($event->pk_evento, $race),
        ];
    }

    /**
     * @return list<int>
     */
    public function scanned(EventosCompras $purchase): array
    {
        return $this->tickets($purchase)
            ->filter(fn (EventosFuncionesBoletos $ticket) => (bool) $ticket->getAttribute('escaneado'))
            ->pluck('pk_evento_funcion_boleto')
            ->values()
            ->all();
    }

    public function owns(EventosCompras $purchase, int $ticketId): bool
    {
        return EventosFuncionesBoletos::query()
            ->visibles()
            ->where('pk_evento_compra', $purchase->pk_evento_compra)
            ->whereKey($ticketId)
            ->exists();
    }

    /**
     * QR dinámico del servicio de acceso (cambia cada pocos minutos para que
     * no sirvan las capturas). Si el servicio no responde se usa el QR fijo.
     */
    public function dynamicQr(int $ticketId): string
    {
        $base = rtrim((string) config('profile.dynamic_qr_url'), '/');

        if ($base !== '') {
            try {
                $response = Http::timeout(5)->get($base.'/qr', ['boleto' => $ticketId]);

                if ($response->successful() && ($image = $this->imageFrom($response->body())) !== null) {
                    return $image;
                }
            } catch (Throwable $exception) {
                Log::warning('No respondió el servicio de QR dinámico.', ['boleto' => $ticketId, 'error' => $exception->getMessage()]);
            }
        }

        return Barcode::qrDataUri($ticketId, 320);
    }

    /**
     * @return Collection<int, EventosFuncionesBoletos>
     */
    private function tickets(EventosCompras $purchase): Collection
    {
        return EventosFuncionesBoletos::query()
            ->visibles()
            ->where('pk_evento_compra', $purchase->pk_evento_compra)
            ->with(['localidad:pk_evento_localidad,evento_localidad', 'localidadEspecial'])
            ->orderBy('pk_evento_funcion_boleto')
            ->get();
    }

    private function event(EventoVista $event, ?EventoFuncionVista $function): array
    {
        return [
            'id' => $event->pk_evento,
            'name' => $event->evento,
            'slug' => (string) $event->getAttribute('friendly_url'),
            'image' => EventImage::url($event->imagen),
            'venue' => $event->escenario,
            'address' => $event->direccionVisible(),
            'city' => $event->ciudad,
            'state' => $event->estado,
            'date' => $function?->fecha_natural,
            'time' => $function?->hora_natural,
            'iso' => $function?->fecha,
        ];
    }

    /**
     * @return array{type: string, label: string, url?: string}
     */
    private function action(EventosCompras $purchase, EventoVista $event): array
    {
        $status = $purchase->status();

        if ($status !== PurchaseStatus::Paid) {
            return ['type' => 'status', 'label' => match ($status) {
                PurchaseStatus::ChargedBack => 'Contracargo aceptado',
                PurchaseStatus::Rejected => 'Pago no completado',
                PurchaseStatus::Cancelled => 'Compra cancelada',
                PurchaseStatus::AwaitingTransfer => 'Esperando transferencia',
                PurchaseStatus::AwaitingPayment => 'Validando pago',
                default => 'Sin boletos',
            }];
        }

        if ((int) $event->pk_evento_status !== self::ACTIVE_EVENT) {
            return ['type' => 'past', 'label' => 'Evento finalizado'];
        }

        if ((int) $event->pk_evento_categoria === self::PDF_CATEGORY) {
            return ['type' => 'pdf', 'label' => 'Descargar boletos', 'url' => route('profile.purchase.pdf', $purchase->codigo)];
        }

        if ((int) $event->bono === 1) {
            return ['type' => 'bond', 'label' => 'Registrar bonos', 'url' => config('boxoffice.bond_registration_url').$purchase->codigo];
        }

        return ['type' => 'ticket', 'label' => 'Ver boletos', 'url' => route('profile.ticket', $purchase->codigo)];
    }

    /**
     * @return list<array{name: string, image: string, url: string|null}>
     */
    private function sponsors(int $eventId, bool $race): array
    {
        $config = (array) config('profile.sponsors');
        $events = (array) ($config['events'] ?? []);

        if (! array_key_exists($eventId, $events) && $race && ($config['hide_on_races'] ?? false)) {
            return [];
        }

        return collect($events[$eventId] ?? $config['default'] ?? [])
            ->filter(fn ($sponsor) => is_array($sponsor) && ! empty($sponsor['image']))
            ->map(fn (array $sponsor) => [
                'name' => (string) ($sponsor['name'] ?? 'Patrocinador'),
                'image' => (string) $sponsor['image'],
                'url' => $sponsor['url'] ?? null,
            ])
            ->values()
            ->all();
    }

    private function seat(EventosFuncionesBoletos $ticket): ?string
    {
        if ($ticket->isGeneralAdmission()) {
            return null;
        }

        return str_contains($ticket->asiento, '-') ? 'Mesa '.$ticket->asiento : $ticket->asiento;
    }

    private function runner(?EventoBoletoCarrera $runner): ?array
    {
        if ($runner === null) {
            return null;
        }

        return [
            'name' => $runner->nombre,
            'number' => $runner->getAttribute('num_participante'),
        ];
    }

    private function imageFrom(string $html): ?string
    {
        if (preg_match('/src=["\'](data:image\/(?:png|svg\+xml|jpeg|gif);base64,[A-Za-z0-9+\/=]+)["\']/i', $html, $match)) {
            return $match[1];
        }

        if (preg_match('/<svg\b.*<\/svg>/is', $html, $match)) {
            return 'data:image/svg+xml;base64,'.base64_encode($match[0]);
        }

        return null;
    }
}
