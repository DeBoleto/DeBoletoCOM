<?php

namespace App\Modules\BoxOffice\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCourtesiesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'costo' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'comentario' => ['required', 'string', 'min:3', 'max:255'],
            'acepto' => ['accepted'],
            'token' => ['required', 'uuid'],
        ];
    }

    public function attributes(): array
    {
        return ['costo' => 'costo del boleto', 'comentario' => 'motivo'];
    }

    public function messages(): array
    {
        return ['acepto.accepted' => 'Confirma la creación de las cortesías.'];
    }
}
