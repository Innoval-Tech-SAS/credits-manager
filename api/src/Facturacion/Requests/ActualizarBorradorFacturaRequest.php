<?php

namespace App\Facturacion\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarBorradorFacturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha' => ['nullable', 'date_format:Y-m-d'],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.concepto' => ['required', 'string', 'max:60'],
            'lineas.*.descripcion' => ['required', 'string', 'max:255'],
            'lineas.*.valor' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
