<?php

namespace App\Facturacion\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FacturarInteresesPagadosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'credito_id' => ['required', 'integer', Rule::exists('creditos', 'id')->where('empresa_id', $this->user()->empresa_id)],
            'cuota_ids' => ['required', 'array', 'min:1'],
            'cuota_ids.*' => ['integer', Rule::exists('cuotas', 'id')->where('credito_id', $this->input('credito_id'))],
        ];
    }
}
