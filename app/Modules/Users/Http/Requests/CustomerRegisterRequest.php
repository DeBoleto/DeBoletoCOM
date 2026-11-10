<?php

namespace App\Modules\Users\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class CustomerRegisterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100'],
            'apellido_paterno' => ['required', 'string', 'max:100'],
            'apellido_materno' => ['nullable', 'string', 'max:100'],
            'phone' => ['required', 'string', 'regex:/^\d{10}$/'],
            'email' => ['required', 'email:rfc', 'max:150', $this->allowedDomain()],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Escribe tu número a 10 dígitos, sin espacios.',
        ];
    }

    private function allowedDomain(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $domain = mb_strtolower((string) str($value)->afterLast('@'));

            if (! in_array($domain, config('checkout.allowed_email_domains'), true)) {
                $fail('Usa un correo de un proveedor conocido (Gmail, Outlook, Hotmail, iCloud o Yahoo).');
            }
        };
    }
}
