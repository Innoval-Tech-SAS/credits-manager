<?php

namespace App\Facturacion\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarConfiguracionFacturacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nota_debito_tope' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
