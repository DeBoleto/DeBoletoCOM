<?php

namespace App\Modules\Dashboard\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPrivilege((int) config('boxoffice.privileges.events_create'));
    }

    public function rules(): array
    {
        return [
            'pk_usuario' => ['required', 'integer', Rule::exists('usuarios', 'pk_usuario')->where('eliminado', 0)->where(fn ($q) => $q->where('pk_usuario_tipo', '<=', 3))],
            'pk_escenario' => ['required', 'integer', Rule::exists('escenarios', 'pk_escenario')->where('eliminado', 0)],
            'pk_escenario_formato' => ['required', 'integer', Rule::exists('escenarios_formatos', 'pk_escenario_formato')->where('pk_escenario', $this->integer('pk_escenario'))],
            'evento' => ['required', 'string', 'max:200'],
            'descripcion' => ['nullable', 'string', 'max:10000'],
            'comentarios' => ['nullable', 'string', 'max:2000'],
            'imagen' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'funciones' => ['required', 'array', 'min:1', 'max:60'],
            'funciones.*' => ['required', 'date_format:Y-m-d H:i', 'distinct'],
            'precios' => ['required', 'array'],
            'precios.*' => ['required', 'numeric', 'min:0', 'max:1000000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'pk_usuario' => 'empresario',
            'pk_escenario' => 'recinto',
            'pk_escenario_formato' => 'formato del recinto',
            'evento' => 'nombre del evento',
            'funciones.*' => 'fecha de función',
            'precios.*' => 'precio',
        ];
    }
}
