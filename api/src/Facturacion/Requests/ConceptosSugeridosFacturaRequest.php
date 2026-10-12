<?php

namespace App\Facturacion\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConceptosSugeridosFacturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'credito_id' => ['required', 'integer', Rule::exists('creditos', 'id')->where('empresa_id', $this->user()->empresa_id)],
        ];
    }
}
