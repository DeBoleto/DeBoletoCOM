<?php

namespace App\Modules\Users\Http\Requests;

use App\Modules\Users\Services\StytchOtp;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestCodeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'lada' => ['required', 'string', 'regex:/^\d{1,3}$/'],
            'telefono' => ['required', 'string', 'regex:/^\d{10}$/'],
            'canal' => ['required', Rule::in([StytchOtp::CHANNEL_WHATSAPP, StytchOtp::CHANNEL_SMS])],
        ];
    }

    public function messages(): array
    {
        return [
            'telefono.regex' => 'Escribe tu número a 10 dígitos, sin espacios.',
            'lada.regex' => 'La lada no es válida.',
        ];
    }
}
