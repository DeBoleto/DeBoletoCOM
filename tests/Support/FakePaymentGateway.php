<?php

namespace Tests\Support;

use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Data\CardChargeRequest;
use App\Modules\Payments\Data\CardDetails;
use App\Modules\Payments\Data\Charge;
use App\Modules\Payments\Data\ChargeStatus;
use App\Modules\Payments\Data\TransferChargeRequest;
use App\Modules\Payments\Data\TransferDetails;
use App\Modules\Users\Models\Usuarios;
use Illuminate\Support\Carbon;

/**
 * Pasarela en memoria: registra los cargos y permite fijar su estado.
 */
class FakePaymentGateway implements PaymentGateway
{
    /** @var array<string, Charge> */
    public array $charges = [];

    /** @var list<CardChargeRequest|TransferChargeRequest> */
    public array $requests = [];

    public ChargeStatus $nextStatus = ChargeStatus::InProgress;

    public ?float $amountOverride = null;

    public function customerFor(Usuarios $user): string
    {
        return 'cus_'.$user->pk_usuario;
    }

    public function cards(string $customerId): array
    {
        return [new CardDetails('card_saved', '411111XXXXXX1111', 'ANA LOPEZ', '12', '30', 'BANCO', 'visa')];
    }

    public function saveCard(string $customerId, string $tokenId, string $deviceSessionId): CardDetails
    {
        return new CardDetails('card_new', '411111XXXXXX1111', 'ANA LOPEZ', '12', '30', 'BANCO', 'visa');
    }

    public function refreshCvv(string $customerId, string $cardId, string $cvv): void {}

    public function chargeCard(string $customerId, CardChargeRequest $request): Charge
    {
        $this->requests[] = $request;

        return $this->store(new Charge(
            'tr'.count($this->requests).'abc',
            $this->nextStatus,
            $this->amountOverride ?? $request->amount,
            $request->orderId,
            'card',
            $this->nextStatus === ChargeStatus::InProgress ? 'https://bank.test/3ds' : null,
            new CardDetails(null, '411111XXXXXX1111', 'ANA LOPEZ', '12', '30', 'BANCO', 'visa'),
        ));
    }

    public function chargeTransfer(string $customerId, TransferChargeRequest $request): Charge
    {
        $this->requests[] = $request;

        return $this->store(new Charge(
            'tr'.count($this->requests).'spei',
            ChargeStatus::InProgress,
            $request->amount,
            $request->orderId,
            'bank_account',
            transfer: new TransferDetails('646180111812345678', 'STP', 'DeBoleto'),
            dueDate: Carbon::parse($request->dueDate),
        ));
    }

    public function charge(string $chargeId): Charge
    {
        return $this->charges[$chargeId];
    }

    public function publicConfig(): array
    {
        return ['merchantId' => 'm123', 'publicKey' => 'pk_test', 'sandbox' => true];
    }

    /**
     * Cambia el estado de un cargo existente (lo que reportaría OpenPay).
     */
    public function settle(string $chargeId, ChargeStatus $status): void
    {
        $charge = $this->charges[$chargeId];

        $this->charges[$chargeId] = new Charge(
            $charge->id, $status, $charge->amount, $charge->orderId, $charge->method,
            null, $charge->card, $charge->transfer, $charge->dueDate,
        );
    }

    private function store(Charge $charge): Charge
    {
        return $this->charges[$charge->id] = $charge;
    }
}
