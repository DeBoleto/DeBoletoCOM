<?php

namespace App\Modules\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Users\Exceptions\OtpException;
use App\Modules\Users\Http\Requests\CompleteRegistrationRequest;
use App\Modules\Users\Http\Requests\CustomerLoginRequest;
use App\Modules\Users\Http\Requests\CustomerRegisterRequest;
use App\Modules\Users\Http\Requests\RequestCodeRequest;
use App\Modules\Users\Http\Requests\VerifyCodeRequest;
use App\Modules\Users\Models\Usuarios;
use App\Modules\Users\Services\StytchOtp;
use App\Modules\Users\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Inicio de sesión de clientes del sitio público (tabla `usuarios`): código
 * por WhatsApp/SMS, correo y contraseña, y registro.
 */
class CustomerAuthController extends Controller
{
    private const OTP_SESSION = 'customer.otp';

    private const VERIFIED_PHONE_SESSION = 'customer.verified_phone';

    private const MAX_CODE_ATTEMPTS = 5;

    private const VERIFIED_PHONE_MINUTES = 15;

    public function __construct(private readonly StytchOtp $otp) {}

    public function requestCode(RequestCodeRequest $request): RedirectResponse
    {
        $phone = $request->validated('telefono');
        $this->throttle("customer-otp:{$phone}", 3, 'telefono', 'Ya enviamos varios códigos a este número. Espera un minuto para pedir otro.');
        $this->throttle('customer-otp-ip:'.$request->ip(), 10, 'telefono', 'Demasiadas solicitudes. Espera un minuto.');

        try {
            $methodId = $this->otp->send(PhoneNumber::e164($phone, $request->validated('lada')), $request->validated('canal'));
        } catch (OtpException $exception) {
            throw ValidationException::withMessages(['telefono' => $exception->getMessage()]);
        }

        $request->session()->put(self::OTP_SESSION, ['method_id' => $methodId, 'phone' => $phone, 'attempts' => 0]);

        $channel = $request->validated('canal') === StytchOtp::CHANNEL_SMS ? 'SMS' : 'WhatsApp';

        return back()->with('success', "Te enviamos un código por {$channel}.");
    }

    public function verifyCode(VerifyCodeRequest $request): RedirectResponse
    {
        $pending = $request->session()->get(self::OTP_SESSION);

        if (! is_array($pending)) {
            throw ValidationException::withMessages(['codigo' => 'Tu verificación expiró. Solicita un código nuevo.']);
        }

        if ($pending['attempts'] >= self::MAX_CODE_ATTEMPTS) {
            $request->session()->forget(self::OTP_SESSION);

            throw ValidationException::withMessages(['codigo' => 'Superaste los intentos permitidos. Solicita un código nuevo.']);
        }

        try {
            $this->otp->verify($pending['method_id'], $request->validated('codigo'));
        } catch (OtpException $exception) {
            $request->session()->put(self::OTP_SESSION.'.attempts', $pending['attempts'] + 1);

            throw ValidationException::withMessages(['codigo' => $exception->getMessage()]);
        }

        $request->session()->forget(self::OTP_SESSION);

        $customer = Usuarios::query()->activos()->conTelefono($pending['phone'])->first();

        if ($customer === null) {
            $request->session()->put(self::VERIFIED_PHONE_SESSION, ['phone' => $pending['phone'], 'at' => now()->timestamp]);

            return back()->with('registration_required', true);
        }

        $this->signIn($request, $customer, loginType: 2);

        return back()->with('success', '¡Hola de nuevo, '.$customer->nombre.'!');
    }

    /**
     * Alta de un cliente cuyo teléfono ya se verificó con código pero no tiene cuenta.
     */
    public function completeRegistration(CompleteRegistrationRequest $request): RedirectResponse
    {
        $verified = $request->session()->get(self::VERIFIED_PHONE_SESSION);

        if (! is_array($verified) || now()->timestamp - $verified['at'] > self::VERIFIED_PHONE_MINUTES * 60) {
            $request->session()->forget(self::VERIFIED_PHONE_SESSION);

            throw ValidationException::withMessages(['correo' => 'Tu verificación expiró. Solicita un código nuevo.']);
        }

        if (Usuarios::query()->activos()->conTelefono($verified['phone'])->exists()) {
            $request->session()->forget(self::VERIFIED_PHONE_SESSION);

            throw ValidationException::withMessages(['correo' => 'Ese teléfono ya tiene una cuenta. Inicia sesión con código.']);
        }

        // Ligar el teléfono a una cuenta existente solo por conocer su correo permitiría
        // entrar a cuentas ajenas: el dueño debe iniciar sesión con su contraseña.
        if ($this->emailTaken($request->validated('correo'))) {
            throw ValidationException::withMessages([
                'correo' => 'Ese correo ya tiene una cuenta. Inicia sesión con tu correo y contraseña.',
            ]);
        }

        $customer = $this->createCustomer([
            'nombre' => $request->validated('nombre'),
            'apellido_paterno' => $request->validated('apellido_paterno'),
            'apellido_materno' => $request->validated('apellido_materno'),
            'correo' => $request->validated('correo'),
            'telefono' => $verified['phone'],
            'password' => Str::password(32),
        ]);

        $request->session()->forget(self::VERIFIED_PHONE_SESSION);
        $this->signIn($request, $customer, loginType: 2);

        return back()->with('success', 'Tu cuenta quedó lista.');
    }

    public function login(CustomerLoginRequest $request): RedirectResponse
    {
        $this->throttle($request->throttleKey(), 5, 'email', 'Demasiados intentos. Espera un minuto.');

        $credentials = [
            'correo' => $request->validated('email'),
            'password' => $request->validated('password'),
            'eliminado' => 0,
        ];

        if (! Auth::guard('customer')->validate($credentials)) {
            throw ValidationException::withMessages(['email' => 'El correo o la contraseña no son correctos.']);
        }

        RateLimiter::clear($request->throttleKey());

        $customer = Usuarios::query()->activos()->where('correo', $request->validated('email'))->firstOrFail();
        $this->signIn($request, $customer, loginType: 1);

        return back()->with('success', '¡Hola de nuevo, '.$customer->nombre.'!');
    }

    public function register(CustomerRegisterRequest $request): RedirectResponse
    {
        if ($this->emailTaken($request->validated('email'))) {
            throw ValidationException::withMessages(['email' => 'Ese correo ya tiene una cuenta. Inicia sesión.']);
        }

        if (Usuarios::query()->activos()->conTelefono($request->validated('phone'))->exists()) {
            throw ValidationException::withMessages(['phone' => 'Ese teléfono ya tiene una cuenta. Inicia sesión con código.']);
        }

        $customer = $this->createCustomer([
            'nombre' => $request->validated('nombre'),
            'apellido_paterno' => $request->validated('apellido_paterno'),
            'apellido_materno' => $request->validated('apellido_materno'),
            'correo' => $request->validated('email'),
            'telefono' => $request->validated('phone'),
            'password' => $request->validated('password'),
        ]);

        $this->signIn($request, $customer, loginType: 1);

        return back()->with('success', 'Tu cuenta quedó lista.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();

        $otherAreasActive = Auth::guard('boxoffice')->check() || Auth::guard('admin')->check() || Auth::guard('web')->check();

        if (! $otherAreasActive) {
            $request->session()->invalidate();
        }

        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    private function signIn(Request $request, Usuarios $customer, int $loginType): void
    {
        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();

        $customer->forceFill(['tipe_login' => $loginType])->save();
    }

    private function createCustomer(array $attributes): Usuarios
    {
        $customer = new Usuarios;
        $customer->forceFill($attributes + [
            'pk_usuario_tipo' => (int) config('checkout.customer_type'),
            'tipe_login' => 2,
        ])->save();

        return $customer;
    }

    private function emailTaken(string $email): bool
    {
        return Usuarios::query()->activos()->where('correo', $email)->exists();
    }

    private function throttle(string $key, int $perMinute, string $field, string $message): void
    {
        if (RateLimiter::tooManyAttempts($key, $perMinute)) {
            throw ValidationException::withMessages([$field => $message]);
        }

        RateLimiter::hit($key, 60);
    }
}
