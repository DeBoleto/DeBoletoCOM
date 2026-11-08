<?php

namespace App\Modules\BoxOffice\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendWhatsAppRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['telefono' => preg_replace('/\D+/', '', (string) $this->input('telefono'))]);
    }

    public function rules(): array
    {
        return ['telefono' => ['required', 'digits:10']];
    }

    public function attributes(): array
    {
        return ['telefono' => 'teléfono'];
    }
}
