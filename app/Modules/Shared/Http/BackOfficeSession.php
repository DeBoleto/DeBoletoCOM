<?php

namespace App\Modules\Shared\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Taquilla y administración usan guards distintos sobre la misma sesión:
 * cerrar una no debe cerrar la otra.
 */
class BackOfficeSession
{
    private const GUARDS = ['boxoffice', 'admin'];

    public static function logout(Request $request, string $guard): void
    {
        Auth::guard($guard)->logout();

        $stillSignedIn = collect(self::GUARDS)
            ->reject(fn (string $other) => $other === $guard)
            ->contains(fn (string $other) => Auth::guard($other)->check());

        if ($stillSignedIn) {
            $request->session()->migrate(true);
        } else {
            $request->session()->invalidate();
        }

        $request->session()->regenerateToken();
    }
}
