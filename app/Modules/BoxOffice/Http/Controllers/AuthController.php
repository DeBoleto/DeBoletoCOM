<?php

namespace App\Modules\BoxOffice\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BoxOffice\Http\Requests\LoginRequest;
use App\Modules\BoxOffice\Services\MenuBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('BoxOffice/Auth/Login');
    }

    public function store(LoginRequest $request, MenuBuilder $menu): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();
        $menu->forget();

        return redirect()->intended(route('boxoffice.dashboard'));
    }

    public function destroy(Request $request, MenuBuilder $menu): RedirectResponse
    {
        $menu->forget();
        Auth::guard('boxoffice')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('boxoffice.login');
    }
}
