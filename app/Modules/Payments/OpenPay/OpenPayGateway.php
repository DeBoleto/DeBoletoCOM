<?php

namespace App\Modules\Payments\OpenPay;

use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Data\CardChargeRequest;
use App\Modules\Payments\Data\CardDetails;
use App\Modules\Payments\Data\Charge;
use App\Modules\Payments\Data\TransferChargeRequest;
use App\Modules\Payments\Exceptions\PaymentException;
use App\Modules\Users\Models\Usuarios;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente de la API REST de OpenPay (México).
 *
 * Los datos de tarjeta nunca pasan por el servidor: el navegador los tokeniza
 * con openpay.js y aquí solo se usan tokens o ids de tarjetas guardadas.
 */
class OpenPayGateway implements PaymentGateway
{
    private const CURRENCY = 'MXN';

    /** Mensajes para el cliente según el código de error de OpenPay. */
    private const MESSAGES = [
        '1006' => 'Esta compra ya tiene un cargo registrado. Revisa tus compras antes de intentar de nuevo.',
        '2004' => 'El número de tarjeta no es válido.',
        '2005' => 'La fecha de vencimiento de la tarjeta ya pasó.',
        '2006' => 'Falta el código de seguridad (CVV) de la tarjeta.',
        '2009' => 'El código de seguridad (CVV) no es válido.',
        '2010' => 'No se pudo completar la autenticación 3D Secure.',
        '2011' => 'Ese tipo de tarjeta no se acepta.',
        '3001' => 'Tu banco rechazó la tarjeta. Intenta con otra o comunícate con tu banco.',
        '3002' => 'La tarjeta está vencida.',
        '3003' => 'La tarjeta no tiene fondos suficientes.',
        '3004' => 'Tu banco rechazó la tarjeta. Intenta con otra o comunícate con tu banco.',
        '3005' => 'El pago fue rechazado por el sistema antifraude.',
        '3006' => 'La operación no está permitida para esta tarjeta.',
        '3008' => 'La tarjeta no permite compras en línea.',
        '3009' => 'Tu banco rechazó la tarjeta. Intenta con otra o comunícate con tu banco.',
        '3010' => 'Tu banco restringió la tarjeta. Comunícate con tu banco.',
        '3011' => 'Tu banco rechazó la tarjeta. Comunícate con tu banco.',
        '3012' => 'Tu banco requiere que autorices el cargo. Comunícate con tu banco.',
    ];

    public function customerFor(Usuarios $user): string
    {
        if ($user->pk_customer) {
            $response = $this->send(fn (PendingRequest $http) => $http->get("customers/{$user->pk_customer}"));

            if ($response->successful()) {
                return $user->pk_customer;
            }

            if ($response->status() !== 404) {
                $this->fail($response);
            }
        }

        $response = $this->send(fn (PendingRequest $http) => $http->post('customers', [
            'name' => $user->nombre,
            'last_name' => trim($user->apellido_paterno.' '.$user->apellido_materno),
            'email' => $user->correo,
            'phone_number' => $user->telefono,
            'external_id' => (string) $user->pk_usuario,
            'requires_account' => false,
        ]));

        // Un external_id repetido significa que el cliente ya existe en OpenPay.
        if ($response->failed() && (string) $response->json('error_code') === '1005') {
            $existing = $this->send(fn (PendingRequest $http) => $http->get('customers', ['external_id' => (string) $user->pk_usuario]));
            $customerId = $existing->json('0.id');
        } else {
            $this->ensureSuccess($response);
            $customerId = $response->json('id');
        }

        if (! is_string($customerId)) {
            throw new PaymentException('No pudimos preparar tu cuenta de pago. Inténtalo de nuevo.');
        }

        $user->forceFill(['pk_customer' => $customerId])->save();

        return $customerId;
    }

    public function cards(string $customerId): array
    {
        $response = $this->send(fn (PendingRequest $http) => $http->get("customers/{$customerId}/cards", ['limit' => 20]));
        $this->ensureSuccess($response);

        return array_map(fn (array $card) => CardDetails::fromOpenPay($card), (array) $response->json());
    }

    public function saveCard(string $customerId, string $tokenId, string $deviceSessionId): CardDetails
    {
        $response = $this->send(fn (PendingRequest $http) => $http->post("customers/{$customerId}/cards", [
            'token_id' => $tokenId,
            'device_session_id' => $deviceSessionId,
        ]));
        $this->ensureSuccess($response);

        return CardDetails::fromOpenPay((array) $response->json());
    }

    public function refreshCvv(string $customerId, string $cardId, string $cvv): void
    {
        $response = $this->send(fn (PendingRequest $http) => $http->put("customers/{$customerId}/cards/{$cardId}", ['cvv2' => $cvv]));
        $this->ensureSuccess($response);
    }

    public function chargeCard(string $customerId, CardChargeRequest $request): Charge
    {
        $payload = [
            'method' => 'card',
            'source_id' => $request->sourceId,
            'amount' => round($request->amount, 2),
            'currency' => self::CURRENCY,
            'description' => $request->description,
            'order_id' => $request->orderId,
            'device_session_id' => $request->deviceSessionId,
            'use_3d_secure' => true,
            'redirect_url' => $request->redirectUrl,
        ];

        if ($request->installments !== null) {
            $payload['payment_plan'] = ['payments' => $request->installments];
        }

        return $this->createCharge($customerId, $payload);
    }

    public function chargeTransfer(string $customerId, TransferChargeRequest $request): Charge
    {
        return $this->createCharge($customerId, [
            'method' => 'bank_account',
            'amount' => round($request->amount, 2),
            'currency' => self::CURRENCY,
            'description' => $request->description,
            'order_id' => $request->orderId,
            'due_date' => $request->dueDate->format('Y-m-d\TH:i:sP'),
        ]);
    }

    public function charge(string $chargeId): Charge
    {
        $response = $this->send(fn (PendingRequest $http) => $http->get("charges/{$chargeId}"));
        $this->ensureSuccess($response);

        return Charge::fromOpenPay((array) $response->json());
    }

    public function publicConfig(): array
    {
        return [
            'merchantId' => config('services.openpay.merchant_id'),
            'publicKey' => config('services.openpay.public_key'),
            'sandbox' => ! config('services.openpay.production'),
        ];
    }

    private function createCharge(string $customerId, array $payload): Charge
    {
        $response = $this->send(fn (PendingRequest $http) => $http->post("customers/{$customerId}/charges", $payload));
        $this->ensureSuccess($response);

        return Charge::fromOpenPay((array) $response->json());
    }

    /**
     * @param  callable(PendingRequest): Response  $request
     */
    private function send(callable $request): Response
    {
        try {
            return $request($this->http());
        } catch (ConnectionException $exception) {
            Log::error('OpenPay no respondió', ['message' => $exception->getMessage()]);

            throw new PaymentException('No pudimos comunicarnos con el procesador de pagos. No se realizó ningún cargo; inténtalo de nuevo.', previous: $exception);
        }
    }

    private function ensureSuccess(Response $response): void
    {
        if ($response->failed()) {
            $this->fail($response);
        }
    }

    private function fail(Response $response): never
    {
        $code = (string) $response->json('error_code');

        Log::warning('OpenPay rechazó la operación', [
            'status' => $response->status(),
            'error_code' => $code,
            'request_id' => $response->json('request_id'),
        ]);

        throw new PaymentException(
            self::MESSAGES[$code] ?? 'No se pudo procesar el pago. Revisa los datos e inténtalo de nuevo.',
            $code !== '' ? $code : null,
        );
    }

    private function http(): PendingRequest
    {
        $config = config('services.openpay');

        if (empty($config['merchant_id']) || empty($config['private_key'])) {
            throw new PaymentException('Los pagos en línea no están disponibles en este momento.');
        }

        $host = $config['production'] ? 'https://api.openpay.mx' : 'https://sandbox-api.openpay.mx';

        return Http::baseUrl("{$host}/v1/{$config['merchant_id']}/")
            ->withBasicAuth($config['private_key'], '')
            ->acceptJson()
            ->asJson()
            ->timeout(45);
    }
}
