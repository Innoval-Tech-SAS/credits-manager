<?php

namespace App\Contabilidad\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Un corte contable (balance de prueba, conciliación de apertura): saldos hasta una fecha. */
class ConsultarCorteContableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hasta' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
