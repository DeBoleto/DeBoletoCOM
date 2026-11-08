<?php

namespace App\Modules\BoxOffice\Http\Requests;

use App\Modules\Orders\Enums\PaymentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Selección de función y asientos para cortesías y cambios de boleto.
 */
class PickSeatsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $max = (int) config('boxoffice.max_tickets_per_sale');

        return [
            'funcion' => ['required', 'integer'],
            'localidad' => ['required', 'integer'],
            'seccion' => ['required', 'string', 'max:100'],
            'asientos' => ['array', "max:{$max}"],
            'asientos.*' => ['string', 'max:40', 'distinct'],
            'cantidad' => ['nullable', 'integer', 'min:1', "max:{$max}"],
            'tipo_pago' => ['nullable', 'integer', Rule::in(array_map(fn (PaymentType $type) => $type->value, PaymentType::boxOffice()))],
        ];
    }

    public function attributes(): array
    {
        return ['funcion' => 'función', 'seccion' => 'sección', 'cantidad' => 'cantidad de boletos', 'tipo_pago' => 'forma de pago'];
    }
}
