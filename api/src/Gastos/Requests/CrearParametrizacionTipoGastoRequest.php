<?php

namespace App\Gastos\Requests;

use App\Contabilidad\Rules\CuentaDeMovimiento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RF-02: si una retención aplica, sus campos (tarifa(s) + cuenta) son obligatorios —
 * no se permite guardar una parametrización "a medias" que luego dejaría causar un
 * gasto sin saber qué contabilizar.
 */
class CrearParametrizacionTipoGastoRequest extends FormRequest
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
            'fecha_desde' => ['required', 'date'],

            'tipo_comprobante_id' => ['required', Rule::exists('tipos_comprobante', 'id')->where('empresa_id', $empresaId)],
            'cuenta_gasto_id' => ['required', new CuentaDeMovimiento($empresaId)],
            'cuenta_cxp_id' => ['required', new CuentaDeMovimiento($empresaId)],
            'cuenta_iva_id' => ['nullable', new CuentaDeMovimiento($empresaId)],
            'tarifa_iva' => ['nullable', 'numeric', 'min:0', 'max:100'],

            'aplica_retefuente' => ['sometimes', 'boolean'],
            'concepto_dian' => ['nullable', 'string', 'max:150'],
            'base_minima_uvt' => ['nullable', 'integer', 'min:0'],
            'tarifa_retefuente_persona_natural' => ['required_if:aplica_retefuente,true', 'nullable', 'numeric', 'min:0', 'max:100'],
            'tarifa_retefuente_persona_juridica' => ['required_if:aplica_retefuente,true', 'nullable', 'numeric', 'min:0', 'max:100'],
            'cuenta_retefuente_id' => [
                'required_if:aplica_retefuente,true', 'nullable',
                new CuentaDeMovimiento($empresaId),
            ],

            'aplica_reteica' => ['sometimes', 'boolean'],
            'cuenta_reteica_id' => [
                'required_if:aplica_reteica,true', 'nullable',
                new CuentaDeMovimiento($empresaId),
            ],

            'aplica_reteiva' => ['sometimes', 'boolean'],
            'tarifa_reteiva' => ['required_if:aplica_reteiva,true', 'nullable', 'numeric', 'min:0', 'max:100'],
            'cuenta_reteiva_id' => [
                'required_if:aplica_reteiva,true', 'nullable',
                new CuentaDeMovimiento($empresaId),
            ],
        ];
    }
}
