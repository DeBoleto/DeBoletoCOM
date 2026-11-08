<?php

namespace App\Modules\BoxOffice\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class SalesRangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'desde' => ['required', 'date_format:Y-m-d'],
            'hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ];
    }

    public function attributes(): array
    {
        return ['desde' => 'fecha inicial', 'hasta' => 'fecha final'];
    }

    public function from(): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', $this->validated('desde'), config('boxoffice.timezone'))->startOfDay();
    }

    public function to(): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', $this->validated('hasta'), config('boxoffice.timezone'))->startOfDay();
    }
}
