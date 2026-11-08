<?php

namespace App\Modules\BoxOffice\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BoxOffice\Policies\BoxOfficeAbilities;
use App\Modules\Events\Models\Eventos;
use App\Modules\Shared\Models\LegacyModel;
use App\Modules\Tickets\Enums\TicketStatus;
use App\Modules\Tickets\Models\EventosContadorBoletos;
use App\Modules\Tickets\Models\EventosFuncionesBoletos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Control de acceso: registra cada boleto escaneado una sola vez.
 */
class AccessCountController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('BoxOffice/AccessCount/Index');
    }

    public function scan(Request $request): JsonResponse
    {
        $code = trim((string) $request->validate(['codigo' => ['required', 'string', 'max:64']])['codigo']);

        return DB::transaction(function () use ($code) {
            $ticket = $this->find($code);

            if ($ticket === null) {
                return $this->reply(false, 'El código no corresponde a ningún boleto.');
            }

            $event = Eventos::query()->find($ticket->pk_evento, ['pk_evento', 'evento', 'pk_evento_status']);

            if ($event === null || Gate::denies(BoxOfficeAbilities::EVENT, $ticket->pk_evento)) {
                return $this->reply(false, 'El boleto no pertenece a tus eventos.');
            }

            if ($event->pk_evento_status !== Eventos::STATUS_PUBLISHED) {
                return $this->reply(false, 'Este evento no está disponible para registrar accesos.', $event->evento);
            }

            if (in_array(TicketStatus::tryFrom($ticket->pk_evento_funcion_boleto_status), [TicketStatus::Blocked, TicketStatus::Replaced], true)) {
                return $this->reply(false, 'Este boleto fue cancelado o reemplazado.', $event->evento);
            }

            $previous = EventosContadorBoletos::query()->where('pk_evento_funcion_boleto', $ticket->pk_evento_funcion_boleto)->first();

            if ($previous !== null) {
                $at = Carbon::parse($previous->creacion_fecha)->format('H:i');

                return $this->reply(false, "Este boleto ya entró a las {$at} h.", $event->evento, $ticket);
            }

            (new EventosContadorBoletos)->forceFill([
                'pk_evento' => $ticket->pk_evento,
                'pk_evento_funcion' => $ticket->pk_evento_funcion,
                'pk_evento_funcion_boleto' => $ticket->pk_evento_funcion_boleto,
            ])->save();

            $ticket->forceFill(['escaneado' => 1])->save();

            return $this->reply(true, 'Acceso registrado.', $event->evento, $ticket, [
                'entered' => EventosContadorBoletos::query()->where('pk_evento', $ticket->pk_evento)->count(),
            ]);
        });
    }

    /**
     * Acepta el folio numérico (con o sin ceros a la izquierda, como en el
     * código de barras impreso) o el código del boleto digital.
     */
    private function find(string $code): ?EventosFuncionesBoletos
    {
        $query = EventosFuncionesBoletos::query()->lockForUpdate();

        if (str_contains($code, '-')) {
            return $query->where('codigo_digital', $code)->first();
        }

        $id = ltrim(preg_replace('/^[A-D]|[A-D]$/i', '', $code), '0');

        return ctype_digit($id) ? $query->find((int) $id) : null;
    }

    private function reply(bool $success, string $message, ?string $event = null, ?EventosFuncionesBoletos $ticket = null, array $extra = []): JsonResponse
    {
        return response()->json([
            'success' => $success,
            'message' => $message,
            'event' => $event,
            'ticket' => $ticket ? [
                'id' => $ticket->pk_evento_funcion_boleto,
                'seat' => $ticket->isGeneralAdmission() ? null : $ticket->asiento,
            ] : null,
            'scannedAt' => Carbon::now(LegacyModel::legacyTimezone())->format('H:i:s'),
        ] + $extra);
    }
}
