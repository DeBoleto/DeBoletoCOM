<?php

namespace App\Modules\BoxOffice\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendTicketsMailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'folio' => ['required', 'integer', 'min:1'],
            'email' => ['required', 'email:rfc', 'max:150'],
        ];
    }

    public function attributes(): array
    {
        return ['folio' => 'folio de compra', 'email' => 'correo electrónico'];
    }
}
