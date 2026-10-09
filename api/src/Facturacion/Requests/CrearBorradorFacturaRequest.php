<?php

namespace App\Facturacion\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrearBorradorFacturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'credito_id' => ['required', 'integer', Rule::exists('creditos', 'id')->where('empresa_id', $this->user()->empresa_id)],
            'fecha' => ['nullable', 'date_format:Y-m-d'],
            'fecha_vencimiento' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:fecha'],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.concepto' => ['required', 'string', 'max:60'],
            'lineas.*.descripcion' => ['required', 'string', 'max:255'],
            'lineas.*.valor' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
