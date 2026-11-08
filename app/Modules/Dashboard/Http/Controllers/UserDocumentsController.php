<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Dashboard\Enums\StaffType;
use App\Modules\Users\Models\Usuarios;
use App\Modules\Users\Models\UsuariosDocumentos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Expediente de documentos de un administrador.
 */
class UserDocumentsController extends Controller
{
    public function index(int $usuario): Response
    {
        $user = $this->admin($usuario);

        return Inertia::render('Admin/Staff/Documents', [
            'user' => ['id' => $user->pk_usuario, 'name' => $user->nombreCompleto()],
            'documents' => UsuariosDocumentos::query()
                ->activos()
                ->where('pk_usuario', $user->pk_usuario)
                ->orderByDesc('creacion_fecha')
                ->get()
                ->map(fn (UsuariosDocumentos $document) => [
                    'id' => $document->pk_usuario_documento,
                    'name' => $document->documento,
                    'description' => $document->descripcion,
                    'private' => $document->privado,
                    'issued' => $document->expedicion_fecha?->format('d/m/Y'),
                    'expires' => $document->vigencia ? $document->vencimiento_fecha?->format('d/m/Y') : null,
                    'expired' => $document->vigencia && $document->vencimiento_fecha?->isPast(),
                ])
                ->values(),
        ]);
    }

    public function store(Request $request, int $usuario): RedirectResponse
    {
        $user = $this->admin($usuario);

        $validated = $request->validate([
            'documento' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'archivo' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'privado' => ['boolean'],
            'notificar' => ['boolean'],
            'vigencia' => ['boolean'],
            'expedicion_fecha' => ['nullable', 'date'],
            'vencimiento_fecha' => ['nullable', 'required_if_accepted:vigencia', 'date', 'after_or_equal:expedicion_fecha'],
        ]);

        $file = $request->file('archivo');
        $name = Str::random(40).'.'.$file->extension();
        $file->storeAs(UsuariosDocumentos::DIRECTORY, $name, 'local');

        (new UsuariosDocumentos)->forceFill([
            'pk_usuario' => $user->pk_usuario,
            'documento' => $validated['documento'],
            'descripcion' => $validated['descripcion'] ?? null,
            'fichero' => $name,
            'privado' => $request->boolean('privado'),
            'notificar' => $request->boolean('notificar'),
            'vigencia' => $request->boolean('vigencia'),
            'expedicion_fecha' => $validated['expedicion_fecha'] ?? null,
            'vencimiento_fecha' => $validated['vencimiento_fecha'] ?? null,
            'eliminado' => 0,
        ])->save();

        return back()->with('success', 'Documento agregado.');
    }

    public function download(int $usuario, int $documento): StreamedResponse
    {
        $user = $this->admin($usuario);
        $document = UsuariosDocumentos::query()->activos()->where('pk_usuario', $user->pk_usuario)->findOrFail($documento);

        abort_unless(Storage::disk('local')->exists($document->path()), 404);

        return Storage::disk('local')->download($document->path(), Str::slug($document->documento).'.'.pathinfo($document->fichero, PATHINFO_EXTENSION));
    }

    public function destroy(int $usuario, int $documento): RedirectResponse
    {
        $user = $this->admin($usuario);
        UsuariosDocumentos::query()->where('pk_usuario', $user->pk_usuario)->findOrFail($documento)
            ->forceFill(['eliminado' => 1])->save();

        return back()->with('success', 'Documento eliminado.');
    }

    private function admin(int $userId): Usuarios
    {
        return Usuarios::query()->activos()->where('pk_usuario_tipo', StaffType::Administrator->value)->findOrFail($userId);
    }
}
