<?php

namespace App\Modules\BoxOffice\Policies;

use App\Modules\Events\Models\UsuariosEventos;
use App\Modules\Orders\Models\EventosCompras;
use App\Modules\Users\Models\Usuarios;
use Illuminate\Support\Facades\Gate;

/**
 * Reglas de acceso de la taquilla. En legacy cualquier usuario autenticado
 * podía consultar, imprimir o reenviar compras de cualquier evento por id.
 */
class BoxOfficeAbilities
{
    public const EVENT = 'boxoffice.event';

    public const PURCHASE = 'boxoffice.purchase';

    public const PRIVILEGE = 'boxoffice.privilege';

    public static function register(): void
    {
        Gate::define(self::EVENT, [self::class, 'accessEvent']);
        Gate::define(self::PURCHASE, [self::class, 'accessPurchase']);
        Gate::define(self::PRIVILEGE, fn (Usuarios $user, int|string $privilegeId) => $user->hasPrivilege((int) $privilegeId));
    }

    public static function accessEvent(Usuarios $user, int $eventId): bool
    {
        if ($user->isAdministrator()) {
            return true;
        }

        return UsuariosEventos::query()
            ->activas()
            ->where('pk_usuario', $user->pk_usuario)
            ->where('pk_evento', $eventId)
            ->exists();
    }

    public static function accessPurchase(Usuarios $user, EventosCompras $purchase): bool
    {
        if ($purchase->pk_usuario === $user->pk_usuario) {
            return true;
        }

        return $purchase->pk_evento !== null && self::accessEvent($user, $purchase->pk_evento);
    }
}
