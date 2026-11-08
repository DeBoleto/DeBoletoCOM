<?php

namespace App\Modules\BoxOffice\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class SellerCutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['fecha' => ['required', 'date_format:Y-m-d']];
    }

    public function cutDate(): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', $this->validated('fecha'), config('boxoffice.timezone'))->startOfDay();
    }
}
