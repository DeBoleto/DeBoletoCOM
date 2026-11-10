<?php

namespace App\Modules\Payments\Contracts;

use App\Modules\Payments\Data\CardChargeRequest;
use App\Modules\Payments\Data\CardDetails;
use App\Modules\Payments\Data\Charge;
use App\Modules\Payments\Data\TransferChargeRequest;
use App\Modules\Payments\Exceptions\PaymentException;
use App\Modules\Users\Models\Usuarios;

interface PaymentGateway
{
    /**
     * Id del cliente en la pasarela; lo crea y lo guarda si no existe.
     *
     * @throws PaymentException
     */
    public function customerFor(Usuarios $user): string;

    /**
     * @return list<CardDetails>
     *
     * @throws PaymentException
     */
    public function cards(string $customerId): array;

    /**
     * Guarda la tarjeta tokenizada en el navegador.
     *
     * @throws PaymentException
     */
    public function saveCard(string $customerId, string $tokenId, string $deviceSessionId): CardDetails;

    /**
     * Registra el CVV antes de cobrar una tarjeta guardada.
     *
     * @throws PaymentException
     */
    public function refreshCvv(string $customerId, string $cardId, string $cvv): void;

    /**
     * Cargo con tarjeta autenticado con 3D Secure.
     *
     * @throws PaymentException
     */
    public function chargeCard(string $customerId, CardChargeRequest $request): Charge;

    /**
     * Cargo por transferencia SPEI con fecha límite.
     *
     * @throws PaymentException
     */
    public function chargeTransfer(string $customerId, TransferChargeRequest $request): Charge;

    /**
     * Estado actual de un cargo, consultado directamente a la pasarela.
     *
     * @throws PaymentException
     */
    public function charge(string $chargeId): Charge;

    /**
     * Datos públicos para tokenizar tarjetas en el navegador.
     *
     * @return array{merchantId: string|null, publicKey: string|null, sandbox: bool}
     */
    public function publicConfig(): array;
}
