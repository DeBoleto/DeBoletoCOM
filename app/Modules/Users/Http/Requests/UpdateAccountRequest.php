<?php

namespace App\Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::guard('customer')->check();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'telefono' => preg_replace('/\D+/', '', (string) $this->input('telefono')),
            'correo' => mb_strtolower(trim((string) $this->input('correo'))),
        ]);
    }

    public function rules(): array
    {
        $id = Auth::guard('customer')->id();
        $unique = fn (string $column) => Rule::unique('usuarios', $column)->ignore($id, 'pk_usuario')->where('eliminado', 0);

        return [
            'nombre' => ['required', 'string', 'max:100'],
            'apellido_paterno' => ['required', 'string', 'max:100'],
            'apellido_materno' => ['nullable', 'string', 'max:100'],
            'correo' => ['required', 'email', 'max:150', $unique('correo')],
            'telefono' => ['required', 'digits:10', $unique('telefono')],
            'nacimiento_fecha' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
        ];
    }

    public function messages(): array
    {
        return [
            'correo.unique' => 'Ese correo ya está registrado en otra cuenta.',
            'telefono.unique' => 'Ese teléfono ya está registrado en otra cuenta.',
        ];
    }

    public function attributes(): array
    {
        return [
            'apellido_paterno' => 'apellido paterno',
            'apellido_materno' => 'apellido materno',
            'telefono' => 'teléfono',
            'nacimiento_fecha' => 'fecha de nacimiento',
        ];
    }
}
