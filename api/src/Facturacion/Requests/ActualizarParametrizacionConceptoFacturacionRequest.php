<?php

namespace App\Facturacion\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Mismo criterio que `CrearParametrizacionTipoGastoRequest`: si una retención aplica, su tarifa y su cuenta son obligatorias. */
class ActualizarParametrizacionConceptoFacturacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $empresaId = $this->user()->empresa_id;

        return [
            'tarifa_iva' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'cuenta_iva_id' => ['nullable', Rule::exists('plan_cuentas', 'id')->where('empresa_id', $empresaId)],

            'aplica_retefuente' => ['sometimes', 'boolean'],
            'base_minima_uvt' => ['nullable', 'integer', 'min:0'],
            'tarifa_retefuente' => ['required_if:aplica_retefuente,true', 'nullable', 'numeric', 'min:0', 'max:100'],
            'cuenta_retefuente_id' => [
                'required_if:aplica_retefuente,true', 'nullable',
                Rule::exists('plan_cuentas', 'id')->where('empresa_id', $empresaId),
            ],

            'aplica_reteica' => ['sometimes', 'boolean'],
            'cuenta_reteica_id' => [
                'required_if:aplica_reteica,true', 'nullable',
                Rule::exists('plan_cuentas', 'id')->where('empresa_id', $empresaId),
            ],

            'aplica_reteiva' => ['sometimes', 'boolean'],
            'tarifa_reteiva' => ['required_if:aplica_reteiva,true', 'nullable', 'numeric', 'min:0', 'max:100'],
            'cuenta_reteiva_id' => [
                'required_if:aplica_reteiva,true', 'nullable',
                Rule::exists('plan_cuentas', 'id')->where('empresa_id', $empresaId),
            ],
        ];
    }
}
