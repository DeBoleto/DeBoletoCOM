<?php

namespace App\Modules\Checkout\Http\Requests;

use Illuminate\Validation\Rule;

class PayRequest extends QuoteRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'metodo' => ['required', Rule::in(['card', 'transfer'])],
            'card_id' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9]+$/'],
            'token_id' => ['nullable', Rule::requiredIf(fn () => $this->input('metodo') === 'card' && ! $this->filled('card_id')), 'string', 'max:64', 'regex:/^[A-Za-z0-9]+$/'],
            'cvv' => ['nullable', 'required_with:card_id', 'regex:/^\d{3,4}$/'],
            'device_session_id' => ['nullable', 'required_if:metodo,card', 'string', 'max:255', 'regex:/^[A-Za-z0-9\-]+$/'],
            'guardar_tarjeta' => ['boolean'],
            'whatsapp' => ['nullable', 'regex:/^\d{10}$/'],
            'acepto' => ['accepted'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('card_id')) {
            $this->merge(['token_id' => null]);
        }
    }

    public function messages(): array
    {
        return [
            'token_id.required' => 'Captura los datos de tu tarjeta.',
            'cvv.required_with' => 'Escribe el código de seguridad de tu tarjeta.',
            'cvv.regex' => 'El código de seguridad tiene 3 o 4 dígitos.',
            'device_session_id.required_if' => 'No pudimos verificar tu dispositivo. Recarga la página e inténtalo de nuevo.',
            'whatsapp.regex' => 'Escribe el número de WhatsApp a 10 dígitos.',
            'acepto.accepted' => 'Debes aceptar los términos y condiciones para continuar.',
        ];
    }

    /**
     * @return array{method: 'card'|'transfer', token_id: ?string, card_id: ?string, cvv: ?string, device_session_id: ?string, save_card: bool, whatsapp: ?string}
     */
    public function payment(): array
    {
        return [
            'method' => $this->validated('metodo'),
            'token_id' => $this->validated('token_id'),
            'card_id' => $this->validated('card_id'),
            'cvv' => $this->validated('cvv'),
            'device_session_id' => $this->validated('device_session_id'),
            'save_card' => $this->boolean('guardar_tarjeta'),
            'whatsapp' => $this->validated('whatsapp'),
        ];
    }
}
