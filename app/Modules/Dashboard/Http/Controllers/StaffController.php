<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Dashboard\Enums\StaffType;
use App\Modules\Dashboard\Http\Requests\StaffRequest;
use App\Modules\Users\Models\Usuarios;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Altas, edición y bajas de usuarios del back-office por tipo
 * (administradores, monitores, empresarios, puntos de venta y usuarios).
 * En legacy eran cinco controladores idénticos.
 */
class StaffController extends Controller
{
    public function index(Request $request, string $tipo): Response
    {
        $type = $this->type($request, $tipo);
        $search = trim((string) $request->query('buscar', ''));
        $showInactive = $request->boolean('inactivos');

        $users = Usuarios::query()
            ->where('pk_usuario_tipo', $type->value)
            ->where('eliminado', $showInactive ? 1 : 0)
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn ($inner) => $inner
                    ->where('nombre', 'like', $like)
                    ->orWhere('apellido_paterno', 'like', $like)
                    ->orWhere('apellido_materno', 'like', $like)
                    ->orWhere('correo', 'like', $like));
            })
            ->orderBy('nombre')
            ->orderBy('apellido_paterno')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Usuarios $user) => [
                'id' => $user->pk_usuario,
                'name' => $user->nombreCompleto(),
                'email' => $user->correo,
                'phone' => $user->telefono,
            ]);

        return Inertia::render('Admin/Staff/Index', [
            'type' => $this->present($type),
            'users' => $users,
            'filters' => ['buscar' => $search, 'inactivos' => $showInactive],
            'canManage' => $this->canManage($request, $type),
        ]);
    }

    public function create(Request $request, string $tipo): Response
    {
        $type = $this->type($request, $tipo, manage: true);

        return Inertia::render('Admin/Staff/Form', ['type' => $this->present($type), 'user' => null]);
    }

    public function store(StaffRequest $request, string $tipo): RedirectResponse
    {
        $type = $this->type($request, $tipo, manage: true);

        $user = new Usuarios;
        $user->forceFill($this->attributes($request) + [
            'pk_usuario_tipo' => $type->value,
            'password' => $request->validated('password'),
            'eliminado' => 0,
            'req_update' => 0,
            'creacion_pk_usuario' => $request->user()->pk_usuario,
            'modificacion_pk_usuario' => $request->user()->pk_usuario,
        ])->save();

        return redirect()->route('admin.staff.index', $tipo)->with('success', ucfirst($type->singular())." {$user->nombreCompleto()} creado.");
    }

    public function edit(Request $request, string $tipo, int $usuario): Response
    {
        $type = $this->type($request, $tipo, manage: true);
        $user = $this->user($type, $usuario);

        return Inertia::render('Admin/Staff/Form', [
            'type' => $this->present($type),
            'user' => [
                'id' => $user->pk_usuario,
                'nombre' => $user->nombre,
                'apellido_paterno' => $user->apellido_paterno,
                'apellido_materno' => $user->apellido_materno,
                'correo' => $user->correo,
                'telefono' => $user->telefono,
                'nacimiento_fecha' => $user->nacimiento_fecha ? substr((string) $user->nacimiento_fecha, 0, 10) : null,
            ],
        ]);
    }

    public function update(StaffRequest $request, string $tipo, int $usuario): RedirectResponse
    {
        $type = $this->type($request, $tipo, manage: true);
        $user = $this->user($type, $usuario);

        $user->forceFill($this->attributes($request) + ['modificacion_pk_usuario' => $request->user()->pk_usuario]);

        if ($request->filled('password')) {
            $user->password = $request->validated('password');
        }

        $user->save();

        return redirect()->route('admin.staff.index', $tipo)->with('success', "Cambios de {$user->nombreCompleto()} guardados.");
    }

    public function destroy(Request $request, string $tipo, int $usuario): RedirectResponse
    {
        $type = $this->type($request, $tipo, manage: true);
        $user = $this->user($type, $usuario);

        abort_if($user->pk_usuario === $request->user()->pk_usuario, 422, 'No puedes desactivar tu propia cuenta.');

        $user->forceFill(['eliminado' => 1, 'modificacion_pk_usuario' => $request->user()->pk_usuario])->save();

        return back()->with('success', "{$user->nombreCompleto()} ya no puede iniciar sesión.");
    }

    public function restore(Request $request, string $tipo, int $usuario): RedirectResponse
    {
        $type = $this->type($request, $tipo, manage: true);
        $user = Usuarios::query()->where('pk_usuario_tipo', $type->value)->findOrFail($usuario);

        $user->forceFill(['eliminado' => 0, 'modificacion_pk_usuario' => $request->user()->pk_usuario])->save();

        return back()->with('success', "{$user->nombreCompleto()} se reactivó.");
    }

    private function attributes(StaffRequest $request): array
    {
        return [
            'nombre' => $request->validated('nombre'),
            'apellido_paterno' => $request->validated('apellido_paterno'),
            'apellido_materno' => $request->validated('apellido_materno'),
            'correo' => $request->validated('correo'),
            'telefono' => $request->validated('telefono'),
            'nacimiento_fecha' => $request->validated('nacimiento_fecha'),
        ];
    }

    /**
     * Solo un administrador puede crear o modificar administradores.
     */
    private function canManage(Request $request, StaffType $type): bool
    {
        return $type !== StaffType::Administrator || $request->user()->pk_usuario_tipo === StaffType::Administrator->value;
    }

    private function type(Request $request, string $section, bool $manage = false): StaffType
    {
        $type = StaffType::fromSection($section);

        abort_if($manage && ! $this->canManage($request, $type), 403);

        return $type;
    }

    private function user(StaffType $type, int $userId): Usuarios
    {
        return Usuarios::query()->activos()->where('pk_usuario_tipo', $type->value)->findOrFail($userId);
    }

    private function present(StaffType $type): array
    {
        return [
            'section' => $type->section(),
            'plural' => $type->plural(),
            'singular' => $type->singular(),
            'isAdmin' => $type === StaffType::Administrator,
        ];
    }
}
