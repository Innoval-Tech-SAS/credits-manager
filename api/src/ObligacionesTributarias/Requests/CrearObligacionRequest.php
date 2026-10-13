<?php

namespace App\ObligacionesTributarias\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrearObligacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $empresaId = $this->user()->empresa_id;

        return [
            'tipo_obligacion_id' => [
                'required',
                Rule::exists('tipos_obligacion_tributaria', 'id')->where('empresa_id', $empresaId)->whereNull('borrado_en'),
                Rule::unique('obligaciones_tributarias', 'tipo_obligacion_id')
                    ->where('empresa_id', $empresaId)
                    ->where('periodo', $this->input('periodo'))
                    ->whereNull('borrado_en'),
            ],
            'periodo' => ['required', 'string', 'max:20'],
            'fecha_limite' => ['required', 'date'],
            'responsable_id' => ['nullable', Rule::exists('usuarios', 'id')->where('empresa_id', $empresaId)->whereNull('borrado_en')],
            'observaciones' => ['nullable', 'string', 'max:500'],
            'dias_alerta_previa' => ['nullable', 'integer', 'min:0', 'max:365'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo_obligacion_id.unique' => __('validation.custom.obligaciones_tributarias.periodo_repetido'),
        ];
    }
}
