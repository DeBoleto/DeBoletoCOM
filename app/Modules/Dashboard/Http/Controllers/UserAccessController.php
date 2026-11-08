<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BoxOffice\Services\MenuBuilder;
use App\Modules\Dashboard\Enums\StaffType;
use App\Modules\Events\Models\Eventos;
use App\Modules\Events\Models\UsuariosEventos;
use App\Modules\Users\Models\Privilegios;
use App\Modules\Users\Models\PrivilegiosCategorias;
use App\Modules\Users\Models\Usuarios;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Eventos asignados y privilegios de un usuario del back-office.
 */
class UserAccessController extends Controller
{
    public function events(string $tipo, int $usuario): Response
    {
        $user = $this->user($tipo, $usuario);

        $assigned = $user->eventos()
            ->orderByDesc('inicio_fecha')
            ->get(['eventos.pk_evento', 'eventos.evento', 'eventos.inicio_fecha', 'eventos.pk_evento_status'])
            ->map(fn (Eventos $event) => [
                'id' => $event->pk_evento,
                'name' => $event->evento,
                'date' => $event->inicio_fecha?->format('d/m/Y'),
                'published' => $event->pk_evento_status === Eventos::STATUS_PUBLISHED,
            ]);

        return Inertia::render('Admin/Staff/Events', [
            'type' => ['section' => $tipo, 'plural' => StaffType::fromSection($tipo)->plural()],
            'user' => ['id' => $user->pk_usuario, 'name' => $user->nombreCompleto()],
            'assigned' => $assigned->values(),
            'available' => Eventos::query()
                ->publicados()
                ->where('eliminado', 0)
                ->whereNotIn('pk_evento', $assigned->pluck('id'))
                ->orderBy('evento')
                ->get(['pk_evento', 'evento'])
                ->map(fn (Eventos $event) => ['id' => $event->pk_evento, 'name' => $event->evento])
                ->values(),
        ]);
    }

    public function assignEvent(Request $request, string $tipo, int $usuario): RedirectResponse
    {
        $user = $this->user($tipo, $usuario);
        $eventId = (int) $request->validate([
            'evento' => ['required', 'integer', Rule::exists('eventos', 'pk_evento')->where('pk_evento_status', Eventos::STATUS_PUBLISHED)],
        ])['evento'];

        $assignment = UsuariosEventos::query()->firstOrNew(['pk_usuario' => $user->pk_usuario, 'pk_evento' => $eventId]);
        $assignment->eliminado = 0;
        $assignment->save();

        return back()->with('success', 'Evento asignado.');
    }

    public function removeEvent(string $tipo, int $usuario, int $evento): RedirectResponse
    {
        $user = $this->user($tipo, $usuario);

        UsuariosEventos::query()
            ->where('pk_usuario', $user->pk_usuario)
            ->where('pk_evento', $evento)
            ->update(['eliminado' => 1]);

        return back()->with('success', 'Evento retirado.');
    }

    public function privileges(Request $request, string $tipo, int $usuario): Response
    {
        $user = $this->user($tipo, $usuario);
        $granted = $user->privilegios()->pluck('privilegios.pk_privilegio');

        return Inertia::render('Admin/Staff/Privileges', [
            'type' => ['section' => $tipo, 'plural' => StaffType::fromSection($tipo)->plural()],
            'user' => ['id' => $user->pk_usuario, 'name' => $user->nombreCompleto()],
            'categories' => PrivilegiosCategorias::query()
                ->with(['privilegios' => fn ($query) => $query->where('activo', 1)->orderBy('menu_orden')])
                ->orderBy('menu_orden')
                ->get()
                ->map(fn (PrivilegiosCategorias $category) => [
                    'name' => $category->privilegio_categoria,
                    'privileges' => $category->privilegios->map(fn (Privilegios $privilege) => [
                        'id' => $privilege->pk_privilegio,
                        'label' => $privilege->etiqueta ?: $privilege->privilegio,
                        'menu' => $privilege->menu,
                    ])->values(),
                ])
                ->filter(fn (array $category) => $category['privileges']->isNotEmpty())
                ->values(),
            'granted' => $granted->values(),
            'canEdit' => $this->canEditPrivileges($request, $user),
        ]);
    }

    public function syncPrivileges(Request $request, MenuBuilder $menu, string $tipo, int $usuario): RedirectResponse
    {
        $user = $this->user($tipo, $usuario);
        abort_unless($this->canEditPrivileges($request, $user), 403);

        $ids = $request->validate([
            'privilegios' => ['array'],
            'privilegios.*' => ['integer', Rule::exists('privilegios', 'pk_privilegio')],
        ])['privilegios'] ?? [];

        DB::transaction(function () use ($user, $ids, $request) {
            DB::table('usuarios_privilegios')->where('pk_usuario', $user->pk_usuario)->delete();

            $now = now(config('boxoffice.timezone'))->toDateTimeString();
            DB::table('usuarios_privilegios')->insert(array_map(fn ($id) => [
                'pk_usuario' => $user->pk_usuario,
                'pk_privilegio' => (int) $id,
                'creacion_pk_usuario' => $request->user()->pk_usuario,
                'creacion_fecha' => $now,
            ], array_unique($ids)));
        });

        $menu->forget();

        return back()->with('success', 'Privilegios actualizados. Se aplican en el siguiente inicio de sesión del usuario.');
    }

    /**
     * Solo administradores cambian privilegios, y nunca los propios.
     */
    private function canEditPrivileges(Request $request, Usuarios $user): bool
    {
        return $request->user()->pk_usuario_tipo === StaffType::Administrator->value
            && $request->user()->pk_usuario !== $user->pk_usuario;
    }

    private function user(string $section, int $userId): Usuarios
    {
        return Usuarios::query()->activos()->where('pk_usuario_tipo', StaffType::fromSection($section)->value)->findOrFail($userId);
    }
}
