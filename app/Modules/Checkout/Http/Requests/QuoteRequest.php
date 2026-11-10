<?php

namespace App\Modules\Checkout\Http\Requests;

use App\Modules\Checkout\Services\QuoteOptions;
use Illuminate\Foundation\Http\FormRequest;

class QuoteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'meses' => ['nullable', 'integer'],
            'defender' => ['boolean'],
            'cupon' => ['nullable', 'string', 'max:40'],
        ];
    }

    public function options(): QuoteOptions
    {
        $installments = $this->validated('meses');

        return new QuoteOptions(
            $installments === null ? null : (int) $installments,
            $this->boolean('defender'),
            $this->validated('cupon'),
        );
    }
}
