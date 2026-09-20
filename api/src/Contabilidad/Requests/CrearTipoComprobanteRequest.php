<?php

namespace App\Contabilidad\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrearTipoComprobanteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codigo' => [
                'required', 'string', 'max:10',
                Rule::unique('tipos_comprobante', 'codigo')->where('empresa_id', $this->user()->empresa_id),
            ],
            'nombre' => ['required', 'string', 'max:100'],
        ];
    }
}
