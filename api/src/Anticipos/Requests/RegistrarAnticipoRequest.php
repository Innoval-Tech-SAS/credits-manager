<?php

namespace App\Anticipos\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrarAnticipoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $empresaId = $this->user()->empresa_id;

        return [
            'tercero_id' => ['required', Rule::exists('terceros', 'id')->where('empresa_id', $empresaId)],
            'fecha' => ['required', 'date'],
            'numero_documento' => ['required', 'string', 'max:60'],
            'concepto' => ['required', 'string', 'max:255'],
            'valor' => ['required', 'numeric', 'min:0.01'],
            'cuenta_anticipo_id' => ['required', Rule::exists('plan_cuentas', 'id')->where('empresa_id', $empresaId)],
            'cuenta_caja_id' => ['required', Rule::exists('plan_cuentas', 'id')->where('empresa_id', $empresaId)],
            'tipo_comprobante_id' => ['required', Rule::exists('tipos_comprobante', 'id')->where('empresa_id', $empresaId)],
        ];
    }
}
