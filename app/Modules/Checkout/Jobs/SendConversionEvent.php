<?php

namespace App\Modules\Checkout\Jobs;

use App\Modules\Events\Models\EventosPixel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envía un evento de la compra a la API de conversiones de Meta por cada
 * píxel configurado en el evento (eventos_pixel).
 */
class SendConversionEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 2;

    /**
     * @param  array<string, string>  $userData  Datos del cliente ya normalizados y cifrados con SHA-256.
     */
    public function __construct(
        public readonly int $eventId,
        public readonly string $name,
        public readonly string $sourceUrl,
        public readonly array $userData = [],
        public readonly ?float $value = null,
    ) {}

    public function handle(): void
    {
        $pixels = EventosPixel::query()->where('pk_evento', $this->eventId)->get();

        foreach ($pixels as $pixel) {
            $event = [
                'event_name' => $this->name,
                'event_time' => now()->timestamp,
                'event_source_url' => $this->sourceUrl,
                'action_source' => 'website',
            ];

            if ($this->userData !== []) {
                $event['user_data'] = $this->userData;
            }

            if ($this->value !== null) {
                $event['custom_data'] = ['currency' => 'MXN', 'value' => round($this->value, 2)];
            }

            try {
                $response = Http::timeout(15)->post(
                    'https://graph.facebook.com/'.config('services.facebook.graph_version')."/{$pixel->pixel}/events",
                    ['data' => [$event], 'access_token' => $pixel->api],
                );

                if ($response->failed()) {
                    Log::warning('Meta rechazó el evento de conversión', ['evento' => $this->eventId, 'status' => $response->status()]);
                }
            } catch (ConnectionException $exception) {
                Log::warning('Meta no respondió', ['evento' => $this->eventId, 'message' => $exception->getMessage()]);
            }
        }
    }

    /**
     * Normaliza y cifra los datos del cliente como pide Meta.
     *
     * @param  array<string, string|null>  $raw
     * @return array<string, string>
     */
    public static function hashUserData(array $raw): array
    {
        $hashed = [];

        foreach ($raw as $key => $value) {
            $normalized = mb_strtolower(trim((string) $value));

            if ($key === 'ph') {
                $normalized = preg_replace('/\D/', '', $normalized);
            }

            if ($normalized !== '') {
                $hashed[$key] = hash('sha256', $normalized);
            }
        }

        return $hashed;
    }
}
