<?php

namespace App\Modules\BoxOffice\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OpenCashRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'monto_apertura' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'caja' => ['nullable', 'string', 'max:50'],
            'comentario' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return ['monto_apertura' => 'monto de apertura'];
    }
}
