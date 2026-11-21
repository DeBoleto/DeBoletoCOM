<?php

namespace App\Modules\BoxOffice\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => trim((string) $this->input('nombre')),
            'apellido' => trim((string) $this->input('apellido')),
            'telefono' => preg_replace('/\D+/', '', (string) $this->input('telefono')),
            'correo' => mb_strtolower(trim((string) $this->input('correo'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100'],
            'apellido' => ['required', 'string', 'max:100'],
            'telefono' => ['required', 'digits:10'],
            'correo' => ['required', 'email:rfc', 'max:150'],
        ];
    }

    public function attributes(): array
    {
        return ['telefono' => 'teléfono'];
    }
}
