<?php

namespace App\Conciliacion\Requests;

use App\Conciliacion\Enums\Banco;
use App\Conciliacion\Enums\TipoCuentaBancaria;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrearCuentaBancariaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $empresaId = $this->user()->empresa_id;

        return [
            'banco' => ['required', Rule::enum(Banco::class)],
            'numero_cuenta' => [
                'required', 'string', 'max:30',
                Rule::unique('cuentas_bancarias', 'numero_cuenta')->where('empresa_id', $empresaId),
            ],
            'tipo_cuenta' => ['required', Rule::enum(TipoCuentaBancaria::class)],
            'alias' => ['nullable', 'string', 'max:80'],
            'plan_cuenta_id' => ['required', Rule::exists('plan_cuentas', 'id')->where('empresa_id', $empresaId)],
            'plan_cuenta_puente_id' => ['nullable', Rule::exists('plan_cuentas', 'id')->where('empresa_id', $empresaId)],
            'activa' => ['sometimes', 'boolean'],
        ];
    }
}
