<?php

namespace App\Contabilidad\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Calca `CrearGastoRequest` (Gastos), con 2 diferencias: `centro_costo_id` es opcional (lo exige o no la propia
 * cuenta de la parametrización, vía `ComprobanteService::exigirRequisitosParametrizados`, no esta regla) y no
 * hay `soporte` (fuera de alcance de este flujo).
 */
class CausarConConceptoRequest extends FormRequest
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
                'nullable',
                Rule::exists('centros_costo', 'id')->where('empresa_id', $empresaId)->where('activo', true)->whereNull('borrado_en'),
            ],
            'fecha' => ['required', 'date'],
            'fecha_vencimiento' => ['nullable', 'date', 'after_or_equal:fecha'],
            'numero_documento' => ['required', 'string', 'max:60'],
            'concepto' => ['nullable', 'string', 'max:255'],
            'valor_bruto' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
