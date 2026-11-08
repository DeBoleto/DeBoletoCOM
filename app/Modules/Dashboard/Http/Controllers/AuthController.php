<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BoxOffice\Services\MenuBuilder;
use App\Modules\Dashboard\Http\Requests\AdminLoginRequest;
use App\Modules\Shared\Http\BackOfficeSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('BoxOffice/Auth/Login', [
            'areaLabel' => 'Administración',
            'tagline' => 'Eventos, recintos, usuarios y permisos de la plataforma.',
            'footnote' => 'Acceso restringido',
            'submitRoute' => 'admin.login.store',
        ]);
    }

    public function store(AdminLoginRequest $request, MenuBuilder $menu): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();
        $menu->forget();

        return redirect()->intended(route('admin.home'));
    }

    public function destroy(Request $request, MenuBuilder $menu): RedirectResponse
    {
        $menu->forget();
        BackOfficeSession::logout($request, 'admin');

        return redirect()->route('admin.login');
    }
}
