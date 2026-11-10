<?php

namespace App\Modules\Checkout\Listeners;

use App\Modules\Checkout\Events\WebPurchasePaid;
use App\Modules\Events\Models\Eventos;
use App\Modules\Orders\Models\EventosCompras;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Aviso de compra al CRM de Liga MX para los partidos (categoría deportiva).
 */
class NotifyLigaMx implements ShouldQueue
{
    public int $tries = 2;

    public function handle(WebPurchasePaid $event): void
    {
        $url = config('services.ligamx.url');
        $purchase = EventosCompras::query()->with(['usuario', 'evento', 'boletos.localidad'])->findOrFail($event->purchaseId);
        $match = $purchase->evento;

        if (! $url || ! $match instanceof Eventos || $match->pk_evento_categoria !== (int) config('checkout.ligamx.category')) {
            return;
        }

        $customer = $purchase->usuario;
        $rules = config('checkout.ligamx');
        $start = $match->inicio_fecha;

        $response = Http::timeout(20)->post($url, [
            'email' => $customer->correo,
            'contact_list' => config('services.ligamx.contact_list'),
            'nombre_completo' => $customer->nombreCompleto(),
            'primer_nombre' => strtok((string) $customer->nombre, ' '),
            'telefono' => null,
            'contact_source' => 'Deboleto',
            'id_transaccion' => $purchase->pk_openpay,
            'equipo_local' => $rules['home_team'],
            'equipo_visitante' => trim(explode('vs', $this->withoutStates($match->evento))[0]),
            'estadio' => $rules['stadium'],
            'estado' => $rules['state'],
            'ciudad_partido' => $rules['city'],
            'fecha_partido' => $start?->toDateString(),
            'hora_partido' => $start?->format('m/d/Y H:i:s'),
            'juego' => $match->evento,
            'cantidad_boletos' => $purchase->boletos->count(),
            'localidad' => $purchase->boletos->first()?->localidad?->evento_localidad,
            'total_compra' => $purchase->total_compra,
            'forma_pago' => $purchase->tarjeta_numero ? 'card' : 'transfer',
            'tipo_tarjeta' => '',
            'estatus_transaccion' => 'Completada',
            'step' => 3,
        ]);

        if ($response->failed()) {
            Log::warning('Liga MX rechazó el aviso de compra', ['compra' => $purchase->pk_evento_compra, 'status' => $response->status()]);
        }
    }

    /**
     * Quita nombres de estados de México del título del partido (port de legacy).
     */
    private function withoutStates(string $title): string
    {
        $pattern = '/(?<!\w)(?:de\s+|del\s+)?(?:estado\s+de\s+m[eé]xico|ciudad\s+de\s+m[eé]xico|edomex|baja\s+california\s+sur|baja\s+california|san\s+luis\s+potos[ií]|quintana\s+roo|nuevo\s+l[eé]on|coahuila|michoac[aá]n|veracruz|aguascalientes|campeche|chiapas|chihuahua|colima|cdmx|durango|guanajuato|guerrero|hidalgo|jalisco|m[eé]xico|morelos|nayarit|oaxaca|puebla|quer[eé]taro|sinaloa|sonora|tabasco|tamaulipas|tlaxcala|yucat[aá]n|zacatecas)(?!\w)/iu';

        return trim((string) preg_replace('/\s+/', ' ', (string) preg_replace($pattern, '', $title)));
    }
}
