<?php

namespace App\Gastos\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrearTarifaReteIcaMunicipioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $empresaId = $this->user()->empresa_id;

        return [
            'municipio_id' => ['required', Rule::exists('municipios', 'id')->where('empresa_id', $empresaId)],
            'actividad_economica_id' => ['required', Rule::exists('actividades_economicas', 'id')->where('empresa_id', $empresaId)],
            'fecha_desde' => ['required', 'date'],
            'tarifa' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
