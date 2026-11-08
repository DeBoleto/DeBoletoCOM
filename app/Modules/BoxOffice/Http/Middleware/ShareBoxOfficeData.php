<?php

namespace App\Modules\BoxOffice\Http\Middleware;

use App\Modules\BoxOffice\Services\MenuBuilder;
use App\Modules\Users\Models\Usuarios;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Comparte con Inertia el usuario y el menú del área del back-office.
 */
class ShareBoxOfficeData
{
    public function __construct(private readonly MenuBuilder $menu) {}

    /**
     * @param  string  $area  Área del back-office (boxoffice | admin); también es el nombre del guard.
     */
    public function handle(Request $request, Closure $next, string $area = 'boxoffice'): Response
    {
        Inertia::share([
            'backoffice' => function () use ($request, $area) {
                /** @var Usuarios|null $user */
                $user = $request->user($area);

                return [
                    'area' => $area,
                    'user' => $user ? [
                        'id' => $user->pk_usuario,
                        'name' => $user->nombreCompleto(),
                        'email' => $user->correo,
                    ] : null,
                    'menu' => $user ? $this->menu->for($user, $area) : [],
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
