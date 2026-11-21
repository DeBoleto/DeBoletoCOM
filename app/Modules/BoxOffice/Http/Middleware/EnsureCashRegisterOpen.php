<?php

namespace App\Modules\BoxOffice\Http\Middleware;

use App\Modules\BoxOffice\Services\CashRegister\CashRegister;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * La venta de boletos exige que el cajero tenga una caja abierta.
 */
class EnsureCashRegisterOpen
{
    public function __construct(private readonly CashRegister $registers) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->registers->current($request->user()) !== null) {
            return $next($request);
        }

        abort_if($request->expectsJson(), 409, 'Abre tu caja para vender boletos.');

        return redirect()->route('boxoffice.cash.open')->with('error', 'Abre tu caja para vender boletos.');
    }
}
