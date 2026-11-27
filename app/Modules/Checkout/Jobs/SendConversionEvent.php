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

    public readonly int $occurredAt;

    /**
     * @param  array<string, string>  $userData  Datos del navegador (IP, user agent, fbp, fbc) y del cliente cifrados con SHA-256.
     * @param  array<string, mixed>  $customData
     * @param  string|null  $metaEventId  Mismo event_id que el píxel del navegador para deduplicar.
     */
    public function __construct(
        public readonly int $eventId,
        public readonly string $name,
        public readonly string $sourceUrl,
        public readonly array $userData = [],
        public readonly array $customData = [],
        public readonly ?string $metaEventId = null,
    ) {
        $this->occurredAt = now()->timestamp;
    }

    public function handle(): void
    {
        $pixels = EventosPixel::query()->where('pk_evento', $this->eventId)->get()
            ->filter(fn (EventosPixel $pixel) => trim((string) $pixel->pixel) !== '' && trim((string) $pixel->api) !== '');

        foreach ($pixels as $pixel) {
            $event = array_filter([
                'event_name' => $this->name,
                'event_time' => $this->occurredAt,
                'event_id' => $this->metaEventId,
                'event_source_url' => $this->sourceUrl,
                'action_source' => 'website',
                'user_data' => $this->userData,
                'custom_data' => $this->customData,
            ], fn ($value) => $value !== null && $value !== []);

            try {
                $response = Http::timeout(15)->post(
                    'https://graph.facebook.com/'.config('services.facebook.graph_version').'/'.trim((string) $pixel->pixel).'/events',
                    ['data' => [$event], 'access_token' => $pixel->api],
                );

                if ($response->failed()) {
                    Log::warning('Meta rechazó el evento de conversión', [
                        'evento' => $this->eventId,
                        'nombre' => $this->name,
                        'status' => $response->status(),
                        'error' => $response->json('error.message'),
                    ]);
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
