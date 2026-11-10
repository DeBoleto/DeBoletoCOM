<?php

namespace App\Modules\Users\Services;

use App\Modules\Users\Exceptions\OtpException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Códigos de un solo uso por WhatsApp o SMS mediante la API de Stytch.
 */
class StytchOtp
{
    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const CHANNEL_SMS = 'sms';

    /**
     * Envía un código y devuelve el identificador del teléfono en Stytch,
     * necesario para validarlo después.
     *
     * @throws OtpException
     */
    public function send(string $e164Phone, string $channel): string
    {
        $response = $this->post("/otps/{$channel}/login_or_create", [
            'phone_number' => $e164Phone,
            'locale' => 'es',
            'expiration_minutes' => (int) config('services.stytch.code_minutes', 5),
        ]);

        $methodId = $response->json('phone_id');

        if ($response->failed() || ! is_string($methodId)) {
            Log::warning('Stytch rechazó el envío del código', ['status' => $response->status(), 'error' => $response->json('error_type')]);

            throw new OtpException('No pudimos enviar el código a ese número. Revisa que esté bien escrito e inténtalo de nuevo.');
        }

        return $methodId;
    }

    /**
     * @throws OtpException cuando el código es incorrecto o expiró.
     */
    public function verify(string $methodId, string $code): void
    {
        $response = $this->post('/otps/authenticate', [
            'method_id' => $methodId,
            'code' => $code,
            'session_duration_minutes' => 5,
        ]);

        if ($response->failed()) {
            throw new OtpException('El código es incorrecto o ya expiró. Solicita uno nuevo si es necesario.');
        }
    }

    private function post(string $path, array $payload): Response
    {
        try {
            return $this->client()->post($path, $payload);
        } catch (ConnectionException $exception) {
            Log::error('Stytch no respondió', ['message' => $exception->getMessage()]);

            throw new OtpException('El servicio de verificación no está disponible. Inténtalo en unos minutos.', previous: $exception);
        }
    }

    private function client(): PendingRequest
    {
        $config = config('services.stytch');

        if (empty($config['project_id']) || empty($config['secret'])) {
            throw new OtpException('El inicio de sesión con código no está disponible en este momento.');
        }

        return Http::baseUrl(rtrim($config['base_url'], '/'))
            ->withBasicAuth($config['project_id'], $config['secret'])
            ->acceptJson()
            ->asJson()
            ->timeout(20);
    }
}
