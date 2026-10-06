<?php

namespace App\Contabilidad\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Un rango de fechas contables (libro diario, estados financieros). */
class ConsultarPeriodoContableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'desde' => ['required', 'date_format:Y-m-d'],
            'hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ];
    }
}
