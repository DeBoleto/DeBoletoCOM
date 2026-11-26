<?php

namespace App\Modules\Users\Services\Wallet;

use Illuminate\Support\Carbon;
use RuntimeException;
use Throwable;

/**
 * Enlace "Guardar en Google Wallet" con los boletos de una compra: un JWT
 * firmado con la cuenta de servicio que trae la clase del evento y un objeto
 * por boleto.
 */
class GoogleWalletPass
{
    private const SAVE_URL = 'https://pay.google.com/gp/v/save/';

    public function enabled(): bool
    {
        $config = config('wallet.google');

        return $config['enabled'] && filled($config['issuer_id']) && is_file((string) $config['credentials']);
    }

    /**
     * @param  array{event: array<string, mixed>, purchase: array<string, mixed>, tickets: list<array<string, mixed>>}  $pass
     */
    public function saveUrl(array $pass, int $functionId): string
    {
        $account = json_decode((string) file_get_contents(config('wallet.google.credentials')), true);

        if (empty($account['client_email']) || empty($account['private_key'])) {
            throw new RuntimeException('La llave de la cuenta de servicio de Google Wallet no es válida.');
        }

        $classId = config('wallet.google.issuer_id').".deboleto-funcion-{$functionId}";

        return self::SAVE_URL.$this->jwt([
            'iss' => $account['client_email'],
            'aud' => 'google',
            'typ' => 'savetowallet',
            'iat' => time(),
            'origins' => [config('app.url')],
            'payload' => [
                'eventTicketClasses' => [$this->eventClass($classId, $pass['event'])],
                'eventTicketObjects' => array_map(fn (array $ticket) => $this->ticketObject($classId, $pass, $ticket), $pass['tickets']),
            ],
        ], $account['private_key']);
    }

    private function eventClass(string $classId, array $event): array
    {
        $start = $this->start($event);

        return array_filter([
            'id' => $classId,
            'issuerName' => config('wallet.organization'),
            'reviewStatus' => 'UNDER_REVIEW',
            'eventName' => $this->text($event['name']),
            'venue' => [
                'name' => $this->text($event['venue'] ?: '—'),
                'address' => $this->text(implode(', ', array_filter([$event['address'] ?? null, $event['city'] ?? null, $event['state'] ?? null])) ?: '—'),
            ],
            'dateTime' => $start ? ['start' => $start->toIso8601String()] : null,
            'logo' => ['sourceUri' => ['uri' => asset('deboletoIcono.png')]],
            'heroImage' => $event['image'] ? ['sourceUri' => ['uri' => $event['image']]] : null,
            'hexBackgroundColor' => config('wallet.colors.background'),
        ], fn ($value) => $value !== null);
    }

    private function ticketObject(string $classId, array $pass, array $ticket): array
    {
        $code = (string) $ticket['id'];

        return array_filter([
            'id' => config('wallet.google.issuer_id').".deboleto-{$pass['purchase']['folio']}-{$ticket['kind']}-{$ticket['number']}",
            'classId' => $classId,
            'state' => $ticket['scanned'] ? 'INACTIVE' : 'ACTIVE',
            'ticketHolderName' => $pass['purchase']['holder'] ?: null,
            'ticketNumber' => $code,
            'seatInfo' => array_filter([
                'section' => $this->text($ticket['locality'] ?: 'General'),
                'seat' => $ticket['seat'] ? $this->text($ticket['seat']) : null,
            ]),
            'barcode' => ['type' => 'QR_CODE', 'value' => $code, 'alternateText' => $code],
            'textModulesData' => array_values(array_filter([
                ['id' => 'folio', 'header' => 'Folio', 'body' => (string) $pass['purchase']['folio']],
                $ticket['special'] ? ['id' => 'access', 'header' => 'Acceso', 'body' => $ticket['special']] : null,
                $ticket['courtesy'] ? ['id' => 'courtesy', 'header' => 'Tipo', 'body' => 'Cortesía'] : null,
            ])),
        ], fn ($value) => $value !== null);
    }

    private function text(string $value): array
    {
        return ['defaultValue' => ['language' => 'es-MX', 'value' => $value]];
    }

    private function jwt(array $claims, string $privateKey): string
    {
        $segments = [
            $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
            $this->base64Url(json_encode($claims, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
        ];

        if (! openssl_sign(implode('.', $segments), $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('No se pudo firmar el pase de Google Wallet: '.openssl_error_string());
        }

        $segments[] = $this->base64Url($signature);

        return implode('.', $segments);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function start(array $event): ?Carbon
    {
        try {
            return filled($event['iso'] ?? null) ? Carbon::parse($event['iso'], config('app.timezone')) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
