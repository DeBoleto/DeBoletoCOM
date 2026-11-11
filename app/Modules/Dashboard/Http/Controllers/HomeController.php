<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Dashboard\Enums\StaffType;
use App\Modules\Events\Models\Eventos;
use App\Modules\Users\Models\Usuarios;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(): Response
    {
        $staff = Usuarios::query()->activos()
            ->selectRaw('pk_usuario_tipo, COUNT(*) as total')
            ->groupBy('pk_usuario_tipo')
            ->pluck('total', 'pk_usuario_tipo');

        return Inertia::render('Admin/Home', [
            'events' => [
                'published' => Eventos::query()->publicados()->count(),
                'drafts' => Eventos::query()->where('pk_evento_status', Eventos::STATUS_DRAFT)->count(),
            ],
            'staff' => collect(StaffType::cases())->map(fn (StaffType $type) => [
                'section' => $type->section(),
                'label' => $type->plural(),
                'total' => (int) ($staff[$type->value] ?? 0),
            ])->values(),
        ]);
    }
}
