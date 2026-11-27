<?php

namespace App\Modules\Checkout\Support;

use App\Modules\Users\Models\Usuarios;

/**
 * Clientes de prueba (CHECKOUT_TEST_CHARGE_USERS): sus compras con tarjeta se
 * cobran en OpenPay por un monto fijo para probar la pasarela en producción.
 * La compra y los boletos se registran con los totales reales.
 */
final class TestCharge
{
    public static function appliesTo(Usuarios|int $customer, string $method = 'card'): bool
    {
        $id = $customer instanceof Usuarios ? (int) $customer->pk_usuario : $customer;

        return $method === 'card' && $id > 0 && in_array($id, self::users(), true);
    }

    public static function amount(): float
    {
        return round(max(0.01, (float) config('checkout.test_charge.amount', 1)), 2);
    }

    /**
     * @return list<int>
     */
    public static function users(): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', explode(',', (string) config('checkout.test_charge.users', ''))),
            fn (int $id) => $id > 0,
        )));
    }
}
