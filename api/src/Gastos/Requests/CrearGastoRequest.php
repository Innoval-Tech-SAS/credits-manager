<?php

namespace App\Gastos\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrearGastoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $empresaId = $this->user()->empresa_id;

        return [
            'tipo_gasto_id' => ['required', Rule::exists('tipos_gasto', 'id')->where('empresa_id', $empresaId)],
            'tercero_id' => ['nullable', Rule::exists('terceros', 'id')->where('empresa_id', $empresaId)],
            'municipio_id' => ['nullable', Rule::exists('municipios', 'id')->where('activo', true)->whereNull('borrado_en')],
            'centro_costo_id' => [
                'required',
                Rule::exists('centros_costo', 'id')->where('empresa_id', $empresaId)->where('activo', true)->whereNull('borrado_en'),
            ],
            'fecha' => ['required', 'date'],
            'fecha_vencimiento' => ['nullable', 'date', 'after_or_equal:fecha'],
            'numero_documento' => ['required', 'string', 'max:60'],
            'concepto' => ['required', 'string', 'max:255'],
            'valor_bruto' => ['required', 'numeric', 'min:0.01'],
            'soporte' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg,webp', 'max:5120'],
        ];
    }
}
