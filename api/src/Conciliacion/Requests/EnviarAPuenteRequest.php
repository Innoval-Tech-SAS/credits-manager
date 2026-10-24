<?php

namespace App\Conciliacion\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EnviarAPuenteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $empresaId = $this->user()->empresa_id;

        return [
            'tipo_comprobante_id' => ['required', Rule::exists('tipos_comprobante', 'id')->where('empresa_id', $empresaId)],
            'observacion' => ['nullable', 'string', 'max:255'],
        ];
    }
}
