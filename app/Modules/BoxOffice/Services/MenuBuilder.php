<?php

namespace App\Modules\BoxOffice\Services;

use App\Modules\Users\Models\Privilegios;
use App\Modules\Users\Models\PrivilegiosCategorias;
use App\Modules\Users\Models\Usuarios;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;

/**
 * Construye el menú lateral a partir de los privilegios del usuario y
 * resuelve qué secciones de /boxoffice puede abrir.
 */
class MenuBuilder
{
    private const SESSION_KEY = 'boxoffice.menu';

    public function __construct(private readonly Session $session) {}

    /**
     * @return list<array{label: string, icon: ?string, items: list<array{label: string, url: string, section: string}>}>
     */
    public function for(Usuarios $user): array
    {
        if ($this->session->has(self::SESSION_KEY)) {
            return $this->session->get(self::SESSION_KEY);
        }

        $privileges = $user->privilegios()->menu()->orderBy('menu_orden')->get();
        $categories = PrivilegiosCategorias::query()
            ->whereIn('pk_privilegio_categoria', $privileges->pluck('pk_privilegio_categoria')->unique())
            ->orderBy('menu_orden')
            ->orderBy('pk_privilegio_categoria')
            ->get();

        $menu = $categories->map(fn (PrivilegiosCategorias $category) => [
            'label' => $category->privilegio_categoria,
            'icon' => $category->menu_icono,
            'items' => $privileges
                ->where('pk_privilegio_categoria', $category->pk_privilegio_categoria)
                ->map(fn (Privilegios $privilege) => [
                    'label' => $privilege->etiqueta,
                    'url' => '/boxoffice/'.ltrim((string) $privilege->menu_url, '/'),
                    'section' => self::sectionOf((string) $privilege->menu_url),
                ])
                ->values()
                ->all(),
        ])->values()->all();

        $this->session->put(self::SESSION_KEY, $menu);

        return $menu;
    }

    /**
     * @return list<string>
     */
    public function sectionsFor(Usuarios $user): array
    {
        return collect($this->for($user))
            ->flatMap(fn (array $category) => array_column($category['items'], 'section'))
            ->merge(config('boxoffice.public_sections', []))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function forget(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }

    public static function sectionOf(string $url): string
    {
        return (string) Str::of($url)->trim('/')->before('/')->before('?')->lower();
    }
}
