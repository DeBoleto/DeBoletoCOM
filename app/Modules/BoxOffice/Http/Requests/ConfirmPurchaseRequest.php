<?php

namespace App\Modules\BoxOffice\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'acepto' => ['accepted'],
            'token' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return ['acepto.accepted' => 'Confirma que los datos de la venta son correctos.'];
    }
}
