<?php

namespace App\Contabilidad\Requests;

use App\Contabilidad\Enums\EventoContable;
use App\Contabilidad\Rules\CuentaDeMovimiento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarReglaContabilizacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'evento' => ['required', Rule::enum(EventoContable::class)],
            'cuenta_debito_id' => ['required', new CuentaDeMovimiento($this->user()->empresa_id)],
            'cuenta_credito_id' => ['required', new CuentaDeMovimiento($this->user()->empresa_id)],
            'tipo_comprobante_id' => [
                'required',
                Rule::exists('tipos_comprobante', 'id')->where('empresa_id', $this->user()->empresa_id),
            ],
        ];
    }
}
