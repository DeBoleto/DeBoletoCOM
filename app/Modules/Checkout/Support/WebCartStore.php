<?php

namespace App\Modules\Checkout\Support;

use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;

/**
 * Carrito de la compra web en sesión, aislado por evento.
 */
class WebCartStore
{
    private const PREFIX = 'checkout.web';

    public function __construct(private readonly Session $session) {}

    public function get(int $eventId): ?WebCart
    {
        $data = $this->session->get(self::PREFIX.".cart.{$eventId}");

        return is_array($data) ? WebCart::fromArray($data) : null;
    }

    public function put(WebCart $cart): void
    {
        $this->session->put(self::PREFIX.".cart.{$cart->eventId()}", $cart->toArray());
    }

    public function forget(int $eventId): void
    {
        $this->session->forget(self::PREFIX.".cart.{$eventId}");
    }

    /**
     * Identificador estable de quien aparta asientos. Sobrevive al cambio de id
     * de sesión que ocurre al iniciar sesión a mitad de la compra.
     */
    public function owner(): string
    {
        $owner = $this->session->get(self::PREFIX.'.owner');

        if (! is_string($owner)) {
            $owner = Str::random(40);
            $this->session->put(self::PREFIX.'.owner', $owner);
        }

        return $owner;
    }
}
