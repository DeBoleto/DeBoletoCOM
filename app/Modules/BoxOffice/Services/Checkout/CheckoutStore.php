<?php

namespace App\Modules\BoxOffice\Services\Checkout;

use Illuminate\Contracts\Session\Session;

/**
 * Persiste el asistente de venta en sesión, aislado por evento para que dos
 * pestañas con eventos distintos no se pisen.
 */
class CheckoutStore
{
    public function __construct(
        private readonly Session $session,
        private readonly string $flow = 'checkout',
    ) {}

    /**
     * Mismo almacenamiento con otro espacio de nombres (cortesías, cambios…).
     */
    public function for(string $flow): self
    {
        return new self($this->session, $flow);
    }

    public function get(int $eventId): ?CheckoutState
    {
        $data = $this->session->get($this->key($eventId));

        return is_array($data) ? CheckoutState::fromArray($data) : null;
    }

    public function put(CheckoutState $state): void
    {
        $this->session->put($this->key($state->eventId), $state->toArray());
    }

    public function forget(int $eventId): void
    {
        $this->session->forget($this->key($eventId));
    }

    public function putValue(string $key, mixed $value): void
    {
        $this->session->put("boxoffice.{$this->flow}.{$key}", $value);
    }

    public function value(string $key): mixed
    {
        return $this->session->get("boxoffice.{$this->flow}.{$key}");
    }

    public function forgetValue(string $key): void
    {
        $this->session->forget("boxoffice.{$this->flow}.{$key}");
    }

    private function key(int $eventId): string
    {
        return "boxoffice.{$this->flow}.event.{$eventId}";
    }

    public function owner(): string
    {
        return $this->session->getId();
    }
}
