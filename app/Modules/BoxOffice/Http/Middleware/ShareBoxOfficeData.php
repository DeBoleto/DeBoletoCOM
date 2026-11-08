<?php

namespace App\Modules\BoxOffice\Http\Middleware;

use App\Modules\BoxOffice\Services\MenuBuilder;
use App\Modules\Users\Models\Usuarios;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class ShareBoxOfficeData
{
    public function __construct(private readonly MenuBuilder $menu) {}

    public function handle(Request $request, Closure $next): Response
    {
        Inertia::share([
            'boxoffice' => function () use ($request) {
                /** @var Usuarios|null $user */
                $user = $request->user('boxoffice');

                return [
                    'user' => $user ? [
                        'id' => $user->pk_usuario,
                        'name' => $user->nombreCompleto(),
                        'email' => $user->correo,
                    ] : null,
                    'menu' => $user ? $this->menu->for($user) : [],
                ];
            },
            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);

        return $next($request);
    }
}
