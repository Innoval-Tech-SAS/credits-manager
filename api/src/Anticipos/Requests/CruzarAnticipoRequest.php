<?php

namespace App\Anticipos\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CruzarAnticipoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $empresaId = $this->user()->empresa_id;

        return [
            'anticipo_id' => ['required', 'integer', Rule::exists('documentos_tercero', 'id')->where('empresa_id', $empresaId)],
            'documento_id' => ['required', 'integer', 'different:anticipo_id', Rule::exists('documentos_tercero', 'id')->where('empresa_id', $empresaId)],
            'valor' => ['required', 'numeric', 'min:0.01'],
            'fecha' => ['required', 'date'],
            'tipo_comprobante_id' => ['required', Rule::exists('tipos_comprobante', 'id')->where('empresa_id', $empresaId)],
        ];
    }
}
