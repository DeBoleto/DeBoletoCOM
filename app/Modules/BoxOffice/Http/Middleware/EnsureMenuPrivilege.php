<?php

namespace App\Modules\BoxOffice\Http\Middleware;

use App\Modules\BoxOffice\Services\MenuBuilder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige que la sección solicitada (/boxoffice/{seccion}/...) esté en el menú
 * de privilegios del usuario. En legacy el menú solo ocultaba enlaces; aquí
 * además se bloquea el acceso directo por URL.
 */
class EnsureMenuPrivilege
{
    public function __construct(private readonly MenuBuilder $menu) {}

    /**
     * @param  string  ...$sections  Secciones que dan acceso; basta con una.
     */
    public function handle(Request $request, Closure $next, string ...$sections): Response
    {
        if (! config('boxoffice.enforce_menu_privileges', true)) {
            return $next($request);
        }

        $sections = $sections ?: [MenuBuilder::sectionOf((string) $request->segment(2))];

        abort_if(array_intersect($sections, $this->menu->sectionsFor($request->user())) === [], 403);

        return $next($request);
    }
}
