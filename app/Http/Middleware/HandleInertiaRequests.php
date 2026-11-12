<?php

namespace App\Http\Middleware;

use App\Modules\Users\Models\Usuarios;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'deboletoUrl' => config('app.deboleto_url'),
            'customer' => fn () => $this->customer(),
            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'registration_required' => (bool) $request->session()->get('registration_required'),
                'auth_required' => (bool) $request->session()->get('auth_required'),
            ],
        ];
    }

    /**
     * Cliente con sesión en el sitio público (tabla usuarios).
     */
    private function customer(): ?array
    {
        $customer = Auth::guard('customer')->user();

        if (! $customer instanceof Usuarios) {
            return null;
        }

        return [
            'id' => $customer->pk_usuario,
            'name' => $customer->nombre,
            'fullName' => $customer->nombreCompleto(),
            'email' => $customer->correo,
            'phone' => $customer->telefono,
        ];
    }
}
