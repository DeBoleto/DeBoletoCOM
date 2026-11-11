<?php

namespace App\Modules\Checkout\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSelectionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'funcion' => ['required', 'integer'],
            'localidad' => ['nullable', 'integer'],
            'seccion' => ['nullable', 'string', 'max:100'],
            'asientos' => ['nullable', 'array', 'max:50'],
            'asientos.*' => ['string', 'max:50', 'distinct'],
            'cantidad' => ['nullable', 'integer', 'min:1', 'max:50'],
            'especiales' => ['nullable', 'array', 'max:50'],
            'especiales.*' => ['nullable', 'integer'],
            'sillas_extra' => ['nullable', 'array', 'max:50'],
            'sillas_extra.*' => ['string', 'max:50'],
            'secciones' => ['nullable', 'array', 'min:1', 'max:20'],
            'secciones.*.localidad' => ['required', 'integer'],
            'secciones.*.seccion' => ['required', 'string', 'max:100'],
            'secciones.*.asientos' => ['nullable', 'array', 'max:50'],
            'secciones.*.asientos.*' => ['string', 'max:50', 'distinct'],
            'secciones.*.cantidad' => ['nullable', 'integer', 'min:1', 'max:50'],
            'secciones.*.especiales' => ['nullable', 'array', 'max:50'],
            'secciones.*.especiales.*' => ['nullable', 'integer'],
            'secciones.*.sillas_extra' => ['nullable', 'array', 'max:50'],
            'secciones.*.sillas_extra.*' => ['string', 'max:50'],
            'cantidades' => ['nullable', 'array', 'max:100'],
            'cantidades.*' => ['integer', 'min:0', 'max:50'],
            'estacionamiento' => ['boolean'],
        ];
    }
}
