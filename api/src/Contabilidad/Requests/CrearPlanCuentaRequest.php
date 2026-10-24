<?php

namespace App\Contabilidad\Requests;

use App\Contabilidad\Enums\NaturalezaCuenta;
use App\Contabilidad\Enums\TipoCuenta;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrearPlanCuentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codigo' => [
                'required', 'string', 'max:20',
                Rule::unique('plan_cuentas', 'codigo')->where('empresa_id', $this->user()->empresa_id),
            ],
            'nombre' => ['required', 'string', 'max:150'],
            'naturaleza' => ['required', Rule::enum(NaturalezaCuenta::class)],
            'nivel' => ['sometimes', 'integer', 'min:1'],
            'cuenta_padre_id' => [
                'nullable',
                Rule::exists('plan_cuentas', 'id')->where('empresa_id', $this->user()->empresa_id),
            ],
            'tipo' => ['required', Rule::enum(TipoCuenta::class)],
            'permite_movimiento' => ['sometimes', 'boolean'],
            'exige_tercero' => ['sometimes', 'boolean'],
            'exige_documento_cruce' => ['sometimes', 'boolean'],
            'exige_vencimiento' => ['sometimes', 'boolean'],
            'exige_centro_costo' => ['sometimes', 'boolean'],
        ];
    }
}
