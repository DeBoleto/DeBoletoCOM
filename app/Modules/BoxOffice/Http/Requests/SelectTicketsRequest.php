<?php

namespace App\Modules\BoxOffice\Http\Requests;

use App\Modules\Events\Models\Views\EventoVista;
use App\Modules\Orders\Enums\PaymentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Paso 1 del asistente de venta. Las reglas dependen de si el evento se vende
 * con mapa (asientos / cupo por sección) o por cantidades por localidad.
 */
class SelectTicketsRequest extends FormRequest
{
    private ?bool $sellsByQuantity = null;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $max = (int) config('boxoffice.max_tickets_per_sale');

        $rules = [
            'funcion' => ['required', 'integer'],
            'tipo_pago' => ['required', 'integer', Rule::in(array_map(fn (PaymentType $type) => $type->value, PaymentType::boxOffice()))],
            'promocion' => ['boolean'],
            'descuento' => ['nullable', 'integer', Rule::in(config('boxoffice.allowed_discounts'))],
        ];

        if ($this->sellsByQuantity()) {
            return $rules + [
                'cantidades' => ['required', 'array'],
                'cantidades.*' => ['integer', 'min:0', "max:{$max}"],
            ];
        }

        return $rules + [
            'secciones' => ['sometimes', 'array', 'min:1', 'max:30'],
            'secciones.*.localidad' => ['required', 'integer'],
            'secciones.*.seccion' => ['required', 'string', 'max:100'],
            'secciones.*.asientos' => ['array', "max:{$max}"],
            'secciones.*.asientos.*' => ['string', 'max:40', 'distinct'],
            'secciones.*.cantidad' => ['nullable', 'integer', 'min:1', "max:{$max}"],
            'secciones.*.especiales' => ['array'],
            'secciones.*.especiales.*' => ['integer'],
            'secciones.*.sillas_extra' => ['array'],
            'secciones.*.sillas_extra.*' => ['string', 'max:40'],
            'localidad' => ['required_without:secciones', 'integer'],
            'seccion' => ['required_without:secciones', 'string', 'max:100'],
            'asientos' => ['array', "max:{$max}"],
            'asientos.*' => ['string', 'max:40', 'distinct'],
            'cantidad' => ['nullable', 'integer', 'min:1', "max:{$max}"],
            'especiales' => ['array'],
            'especiales.*' => ['integer'],
            'sillas_extra' => ['array'],
            'sillas_extra.*' => ['string', 'max:40'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ((int) $this->input('tipo_pago') === PaymentType::Transfer->value
                    && ! $this->user()->hasPrivilege((int) config('boxoffice.privileges.transfer_payment'))) {
                    $validator->errors()->add('tipo_pago', 'No tienes permiso para registrar pagos por transferencia.');
                }

                if ($this->sellsByQuantity()) {
                    $total = array_sum(array_map('intval', (array) $this->input('cantidades', [])));

                    if ($total < 1 || $total > (int) config('boxoffice.max_tickets_per_sale')) {
                        $validator->errors()->add('cantidades', 'Indica al menos un boleto y no más de '.config('boxoffice.max_tickets_per_sale').'.');
                    }

                    return;
                }

                $sections = $this->has('secciones') ? (array) $this->input('secciones') : [$this->all()];

                foreach ($sections as $section) {
                    if (empty($section['asientos'] ?? null) && empty($section['cantidad'] ?? null)) {
                        $validator->errors()->add('asientos', 'Selecciona al menos un asiento.');

                        return;
                    }
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'funcion' => 'función',
            'tipo_pago' => 'forma de pago',
            'descuento' => 'descuento',
            'localidad' => 'localidad',
            'seccion' => 'sección',
            'asientos' => 'asientos',
            'cantidad' => 'cantidad de boletos',
            'cantidades' => 'cantidades',
        ];
    }

    public function paymentType(): PaymentType
    {
        return PaymentType::from((int) $this->validated('tipo_pago'));
    }

    public function discount(): ?int
    {
        $discount = $this->validated('descuento');

        return $discount === null ? null : (int) $discount;
    }

    public function sellsByQuantity(): bool
    {
        return $this->sellsByQuantity ??= (bool) EventoVista::query()
            ->find((int) $this->route('evento'))
            ?->sellsWithoutSeatMap();
    }
}
