<?php

namespace App\Modules\Checkout\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HoldSeatRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'funcion' => ['required', 'integer'],
            'seccion' => ['required', 'string', 'max:100'],
            'asiento' => ['required', 'string', 'max:50'],
            'accion' => ['required', Rule::in(['apartar', 'liberar'])],
        ];
    }
}
