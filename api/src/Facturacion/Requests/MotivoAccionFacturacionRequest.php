<?php

namespace App\Facturacion\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Anulación de una factura y reversa de un cobro: toda corrección lleva motivo. */
class MotivoAccionFacturacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'max:500'],
        ];
    }
}
