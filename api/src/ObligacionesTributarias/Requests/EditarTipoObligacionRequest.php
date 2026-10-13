<?php

namespace App\ObligacionesTributarias\Requests;

use App\ObligacionesTributarias\Enums\PeriodicidadObligacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EditarTipoObligacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => [
                'required', 'string', 'max:120',
                Rule::unique('tipos_obligacion_tributaria', 'nombre')
                    ->where('empresa_id', $this->user()->empresa_id)
                    ->whereNull('borrado_en')
                    ->ignore($this->route('tipo')),
            ],
            'periodicidad' => ['required', Rule::enum(PeriodicidadObligacion::class)],
            'dias_alerta_previa' => ['required', 'integer', 'min:0', 'max:365'],
            'activo' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.unique' => __('validation.custom.obligaciones_tributarias.nombre_repetido'),
        ];
    }
}
