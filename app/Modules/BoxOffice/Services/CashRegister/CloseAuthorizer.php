<?php

namespace App\Modules\BoxOffice\Services\CashRegister;

use App\Modules\Users\Models\Usuarios;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Valida las credenciales del supervisor que autoriza un cierre de caja sin
 * cambiar la sesión del cajero.
 */
class CloseAuthorizer
{
    private const MAX_ATTEMPTS = 5;

    public function authorize(Usuarios $cashier, string $email, string $password): Usuarios
    {
        $key = "boxoffice-cash-close|{$cashier->pk_usuario}";

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'autoriza_correo' => 'Demasiados intentos. Intenta de nuevo en '.RateLimiter::availableIn($key).' segundos.',
            ]);
        }

        $provider = Auth::guard('boxoffice')->getProvider();
        $user = $provider->retrieveByCredentials(['correo' => mb_strtolower(trim($email)), 'eliminado' => 0]);

        if (! $user instanceof Usuarios || ! $provider->validateCredentials($user, ['password' => $password])) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages(['autoriza_correo' => 'Las credenciales del supervisor no son válidas.']);
        }

        if ($user->pk_usuario === $cashier->pk_usuario) {
            throw ValidationException::withMessages(['autoriza_correo' => 'El cierre lo debe autorizar otra persona.']);
        }

        if (! $this->canAuthorize($user)) {
            throw ValidationException::withMessages(['autoriza_correo' => 'Ese usuario no puede autorizar cierres de caja.']);
        }

        RateLimiter::clear($key);

        return $user;
    }

    private function canAuthorize(Usuarios $user): bool
    {
        $privilege = config('boxoffice.privileges.cash_close_authorize');

        return $user->isAdministrator()
            || $user->isPromoter()
            || ($privilege !== null && $user->hasPrivilege((int) $privilege));
    }
}
