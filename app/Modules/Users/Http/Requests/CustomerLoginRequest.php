<?php

namespace App\Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomerLoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:150'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    public function throttleKey(): string
    {
        return 'customer-login:'.mb_strtolower((string) $this->input('email')).'|'.$this->ip();
    }
}
