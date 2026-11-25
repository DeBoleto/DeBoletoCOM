<?php

namespace App\Modules\Users\Http\Middleware;

use App\Modules\Users\Models\Usuarios;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Las páginas del perfil piden sesión de cliente: sin ella se vuelve al inicio
 * con el aviso para abrir el acceso y, al entrar, se regresa a la página pedida.
 */
class EnsureCustomer
{
    public const INTENDED = 'customer.intended';

    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('customer')->user() instanceof Usuarios) {
            return $next($request);
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            abort(401);
        }

        $request->session()->put(self::INTENDED, $request->fullUrl());

        return redirect()->route('home')->with('auth_required', true);
    }
}
