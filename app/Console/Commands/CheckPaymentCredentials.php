<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Revisa que las llaves de OpenPay y Stripe del .env sean válidas haciendo
 * consultas de solo lectura: no crea cargos, clientes ni tarjetas.
 */
class CheckPaymentCredentials extends Command
{
    protected $signature = 'payments:check {--only= : openpay o stripe}';

    protected $description = 'Valida las credenciales de OpenPay y Stripe sin hacer cargos';

    private bool $failed = false;

    public function handle(): int
    {
        $only = $this->option('only');

        if ($only === null || $only === 'openpay') {
            $this->openPay();
        }

        if ($only === null || $only === 'stripe') {
            $this->stripe();
        }

        $this->newLine();
        $this->failed ? $this->error('Hay credenciales con problemas.') : $this->info('Todas las credenciales revisadas funcionan.');

        return $this->failed ? self::FAILURE : self::SUCCESS;
    }

    private function openPay(): void
    {
        $config = config('services.openpay');
        $production = (bool) $config['production'];
        $host = $production ? 'https://api.openpay.mx' : 'https://sandbox-api.openpay.mx';

        $this->line('<options=bold>OpenPay</> ('.($production ? 'producción' : 'sandbox').", {$host})");

        if (! $this->present(['OPENPAY_MERCHANT_ID' => $config['merchant_id'], 'OPENPAY_PRIVATE_KEY' => $config['private_key'], 'OPENPAY_PUBLIC_KEY' => $config['public_key']])) {
            return;
        }

        $merchant = $this->request(fn () => Http::withBasicAuth($config['private_key'], '')->acceptJson()->timeout(20)->get("{$host}/v1/{$config['merchant_id']}"));

        if ($merchant?->successful()) {
            $this->ok('Llave privada y Merchant ID', trim(($merchant->json('name') ?? '').' · estado '.($merchant->json('status') ?? '—'), ' ·'));
        } elseif ($merchant !== null) {
            $this->bad('Llave privada y Merchant ID', $this->openPayError($merchant, $production));
        }

        // Con la llave pública solo se pueden crear tokens: una petición vacía
        // responde 400 si la llave es válida y 401 si no.
        $token = $this->request(fn () => Http::withBasicAuth($config['public_key'], '')->acceptJson()->asJson()->timeout(20)->post("{$host}/v1/{$config['merchant_id']}/tokens", []));

        if ($token !== null) {
            $token->status() === 401 || $token->status() === 404
                ? $this->bad('Llave pública', $this->openPayError($token, $production))
                : $this->ok('Llave pública', 'aceptada por OpenPay');
        }

        $config['webhook_user'] && $config['webhook_password']
            ? $this->ok('Webhook', 'usuario y contraseña configurados')
            : $this->warn('  ! Webhook: faltan OPENPAY_WEBHOOK_USER u OPENPAY_WEBHOOK_PASSWORD; OpenPay no podrá avisar los pagos.');
    }

    private function stripe(): void
    {
        $this->newLine();
        $config = config('services.stripe');
        $this->line('<options=bold>Stripe</>');

        if (! $this->present(['STRIPE_KEY' => $config['key'], 'STRIPE_SECRET' => $config['secret']])) {
            return;
        }

        $live = str_contains((string) $config['secret'], '_live_');

        if (str_contains((string) $config['key'], '_live_') !== $live) {
            $this->bad('Modo', 'STRIPE_KEY y STRIPE_SECRET son de modos distintos (una de prueba y otra en vivo).');
        }

        $balance = $this->request(fn () => Http::withToken($config['secret'])->acceptJson()->timeout(20)->get('https://api.stripe.com/v1/balance'));

        if ($balance?->successful()) {
            $this->ok('Llave secreta', ($balance->json('livemode') ? 'en vivo' : 'de prueba').' · '.implode(', ', array_map(
                fn (array $amount) => strtoupper($amount['currency']).' '.number_format($amount['amount'] / 100, 2),
                (array) $balance->json('available'),
            )));
        } elseif ($balance !== null) {
            $this->bad('Llave secreta', $balance->json('error.message') ?? "HTTP {$balance->status()}");
        }

        $token = $this->request(fn () => Http::withToken($config['key'])->acceptJson()->asForm()->timeout(20)->post('https://api.stripe.com/v1/tokens', []));

        if ($token !== null) {
            $token->status() === 401
                ? $this->bad('Llave pública', $token->json('error.message') ?? 'Stripe la rechazó')
                : $this->ok('Llave pública', 'aceptada por Stripe');
        }
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function present(array $values): bool
    {
        $missing = array_keys(array_filter($values, fn ($value) => blank($value)));

        if ($missing !== []) {
            $this->bad('Configuración', 'faltan '.implode(', ', $missing).' en el .env (o hay que correr php artisan config:clear).');

            return false;
        }

        return true;
    }

    private function request(callable $send): ?Response
    {
        try {
            return $send();
        } catch (ConnectionException $exception) {
            $this->bad('Conexión', 'no se pudo conectar: '.$exception->getMessage());

            return null;
        }
    }

    private function openPayError(Response $response, bool $production): string
    {
        $message = $response->json('description') ?? "HTTP {$response->status()}";

        return match ($response->status()) {
            401 => "{$message}. Revisa la llave y que OPENPAY_PRODUCTION=".($production ? 'true' : 'false').' corresponda a esas llaves.',
            404 => "{$message}. Revisa OPENPAY_MERCHANT_ID.",
            default => $message,
        };
    }

    private function ok(string $label, string $detail): void
    {
        $this->line("  <fg=green>✓</> {$label}: {$detail}");
    }

    private function bad(string $label, string $detail): void
    {
        $this->failed = true;
        $this->line("  <fg=red>✗</> {$label}: {$detail}");
    }
}
