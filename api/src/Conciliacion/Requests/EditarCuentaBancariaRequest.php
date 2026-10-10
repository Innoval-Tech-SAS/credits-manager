<?php

namespace App\Conciliacion\Requests;

use App\Conciliacion\Enums\Banco;
use App\Conciliacion\Enums\TipoCuentaBancaria;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EditarCuentaBancariaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $empresaId = $this->user()->empresa_id;

        return [
            'banco' => ['sometimes', Rule::enum(Banco::class)],
            'numero_cuenta' => [
                'sometimes', 'string', 'max:30',
                Rule::unique('cuentas_bancarias', 'numero_cuenta')
                    ->where('empresa_id', $empresaId)
                    ->ignore($this->route('cuenta_bancaria')),
            ],
            'tipo_cuenta' => ['sometimes', Rule::enum(TipoCuentaBancaria::class)],
            'alias' => ['nullable', 'string', 'max:80'],
            'plan_cuenta_id' => ['sometimes', Rule::exists('plan_cuentas', 'id')->where('empresa_id', $empresaId)],
            'plan_cuenta_puente_id' => ['nullable', Rule::exists('plan_cuentas', 'id')->where('empresa_id', $empresaId)],
            'activa' => ['sometimes', 'boolean'],
        ];
    }
}
