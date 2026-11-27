<?php

namespace App\Modules\Checkout\Tracking;

use App\Modules\Checkout\Jobs\SendConversionEvent;
use App\Modules\Users\Models\Usuarios;
use App\Modules\Users\Support\PhoneNumber;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Registra un evento de Meta en los dos canales con el mismo event_id para
 * que Meta los deduplique: lo deja en la sesión para que el píxel del
 * navegador lo dispare en la siguiente página que se pinte y lo manda a la
 * API de conversiones en cola.
 */
class MetaTracker
{
    private const SESSION_KEY = 'meta_pixel.pending';

    private const MAX_PENDING = 20;

    public function __construct(private MetaPixels $pixels) {}

    /**
     * @param  array<string, mixed>  $customData
     */
    public function track(
        int $eventId,
        string $name,
        array $customData = [],
        ?string $metaEventId = null,
        bool $server = true,
        ?string $sourceUrl = null,
        ?array $userData = null,
    ): ?string {
        $pixels = $this->pixels->forEvent($eventId);

        if ($pixels['browser'] === [] && $pixels['server'] === []) {
            return null;
        }

        $metaEventId ??= (string) Str::uuid();
        $customData = ['content_ids' => [(string) $eventId], 'content_type' => 'product', ...$customData];

        if ($pixels['browser'] !== [] && request()->hasSession()) {
            $pending = request()->session()->get(self::SESSION_KEY, []);
            $pending[] = ['pixels' => $pixels['browser'], 'name' => $name, 'eventId' => $metaEventId, 'data' => $customData];
            request()->session()->put(self::SESSION_KEY, array_slice($pending, -self::MAX_PENDING));
        }

        if ($server && $pixels['server'] !== []) {
            SendConversionEvent::dispatch(
                $eventId,
                $name,
                $sourceUrl ?? request()->fullUrl(),
                $userData ?? $this->userData(),
                $customData,
                $metaEventId,
            );
        }

        return $metaEventId;
    }

    /**
     * Eventos pendientes para el píxel del navegador; se vacían al leerlos.
     *
     * @return list<array>
     */
    public function pull(): array
    {
        if (! request()->hasSession()) {
            return [];
        }

        return array_values((array) request()->session()->pull(self::SESSION_KEY, []));
    }

    /**
     * InitiateCheckout una sola vez por evento y sesión, no en cada recarga.
     */
    public function initiateCheckout(int $eventId): void
    {
        $key = "meta_pixel.initiated.{$eventId}";

        if (! request()->hasSession() || request()->session()->has($key)) {
            return;
        }

        request()->session()->put($key, true);
        $this->track($eventId, 'InitiateCheckout');
    }

    /**
     * Datos del navegador (IP, user agent, cookies de Meta) y del cliente con
     * sesión, como los pide la API de conversiones.
     *
     * @return array<string, string>
     */
    public function userData(?Usuarios $customer = null): array
    {
        $request = request();
        $customer ??= Auth::guard('customer')->user();

        $browser = array_filter([
            'client_ip_address' => (string) $request->ip(),
            'client_user_agent' => (string) $request->userAgent(),
            'fbp' => (string) $request->cookie('_fbp'),
            'fbc' => $this->fbc(),
        ], fn ($value) => $value !== '');

        return $browser + ($customer instanceof Usuarios ? self::customerData($customer) : []);
    }

    /**
     * El pago puede confirmarse en un webhook, sin el navegador del cliente:
     * se guardan sus datos al pagar para usarlos en el evento Purchase.
     */
    public function rememberContext(int $customerId, int $eventId): void
    {
        Cache::put(self::contextKey($customerId, $eventId), $this->userData(), now()->addDays(2));
    }

    /**
     * @return array<string, string>
     */
    public static function rememberedContext(int $customerId, int $eventId): array
    {
        return (array) Cache::get(self::contextKey($customerId, $eventId), []);
    }

    /**
     * @return array<string, string>
     */
    public static function customerData(Usuarios $customer): array
    {
        $phone = preg_replace('/\D/', '', (string) $customer->telefono);

        return SendConversionEvent::hashUserData([
            'em' => $customer->correo,
            'ph' => $phone !== '' ? PhoneNumber::e164($phone) : '',
            'fn' => $customer->nombre,
            'ln' => trim($customer->apellido_paterno.' '.$customer->apellido_materno),
            'country' => 'mx',
            'external_id' => (string) $customer->pk_usuario,
        ]);
    }

    private function fbc(): string
    {
        $cookie = (string) request()->cookie('_fbc');

        if ($cookie !== '') {
            return $cookie;
        }

        $clickId = trim((string) request()->query('fbclid', ''));

        return $clickId !== '' ? 'fb.1.'.(int) (microtime(true) * 1000).'.'.$clickId : '';
    }

    private static function contextKey(int $customerId, int $eventId): string
    {
        return "meta_pixel.context:{$customerId}:{$eventId}";
    }
}
