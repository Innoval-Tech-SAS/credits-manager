<?php

namespace App\Contabilidad\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConsultarDocumentosCruceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $empresaId = $this->user()->empresa_id;

        return [
            'cuenta_id' => ['required', 'integer', Rule::exists('plan_cuentas', 'id')->where('empresa_id', $empresaId)->whereNull('borrado_en')],
            'tercero_id' => ['required', 'integer', Rule::exists('terceros', 'id')->where('empresa_id', $empresaId)->whereNull('borrado_en')],
        ];
    }
}
