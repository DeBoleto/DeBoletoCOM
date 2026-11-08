<?php

namespace App\Modules\Dashboard\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['correo' => mb_strtolower(trim((string) $this->input('correo')))]);
    }

    public function rules(): array
    {
        $userId = $this->route('usuario');

        return [
            'nombre' => ['required', 'string', 'max:100'],
            'apellido_paterno' => ['required', 'string', 'max:100'],
            'apellido_materno' => ['nullable', 'string', 'max:100'],
            'correo' => ['required', 'email', 'max:150', Rule::unique('usuarios', 'correo')->ignore($userId, 'pk_usuario')],
            'telefono' => ['nullable', 'regex:/^\d{10}$/'],
            'nacimiento_fecha' => ['nullable', 'date', 'before:today'],
            'password' => [$userId ? 'nullable' : 'required', 'confirmed', Password::min(8)->letters()->numbers()],
        ];
    }

    public function attributes(): array
    {
        return [
            'apellido_paterno' => 'apellido paterno',
            'apellido_materno' => 'apellido materno',
            'correo' => 'correo electrónico',
            'telefono' => 'teléfono',
            'nacimiento_fecha' => 'fecha de nacimiento',
            'password' => 'contraseña',
        ];
    }

    public function messages(): array
    {
        return ['telefono.regex' => 'El teléfono debe tener 10 dígitos.'];
    }
}
