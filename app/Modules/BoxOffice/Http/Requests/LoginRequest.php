<?php

namespace App\Modules\BoxOffice\Http\Requests;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    private const MAX_ATTEMPTS = 5;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'correo' => ['required', 'string', 'email', 'max:150'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['correo' => 'correo electrónico', 'password' => 'contraseña'];
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $credentials = [
            'correo' => $this->string('correo')->lower()->trim()->value(),
            'password' => $this->string('password')->value(),
            'eliminado' => 0,
        ];

        if (! Auth::guard('boxoffice')->attempt($credentials)) {
            RateLimiter::hit($this->throttleKey(), 60);

            throw ValidationException::withMessages([
                'correo' => 'Las credenciales no coinciden con nuestros registros.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'correo' => "Demasiados intentos. Intenta de nuevo en {$seconds} segundos.",
        ]);
    }

    private function throttleKey(): string
    {
        return 'boxoffice-login|'.Str::transliterate(Str::lower($this->string('correo')->value()).'|'.$this->ip());
    }
}
