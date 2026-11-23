<?php

namespace App\Modules\BoxOffice\Http\Requests;

/**
 * Confirmación de una venta de taquilla con los datos del cliente al que se
 * asigna la compra.
 */
class ConfirmSaleRequest extends AssignCustomerRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'acepto' => ['accepted'],
            'token' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return ['acepto.accepted' => 'Confirma que los datos de la venta son correctos.'];
    }

    /**
     * @return array{nombre: string, apellido: string, telefono: string, correo: string}
     */
    public function customer(): array
    {
        return $this->safe()->only(['nombre', 'apellido', 'telefono', 'correo']);
    }
}
