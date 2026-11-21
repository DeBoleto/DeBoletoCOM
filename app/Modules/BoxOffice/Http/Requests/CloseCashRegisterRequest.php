<?php

namespace App\Modules\BoxOffice\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CloseCashRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'efectivo_contado' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'comentario' => ['nullable', 'string', 'max:500'],
            'autoriza_correo' => ['required', 'string', 'email', 'max:150'],
            'autoriza_password' => ['required', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'efectivo_contado' => 'efectivo contado',
            'autoriza_correo' => 'correo del supervisor',
            'autoriza_password' => 'contraseña del supervisor',
        ];
    }
}
