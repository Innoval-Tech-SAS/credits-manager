<?php

namespace App\Gastos\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConsultarReporteGastosCentroCostoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'desde' => ['required', 'date_format:Y-m'],
            'hasta' => ['required', 'date_format:Y-m', 'after_or_equal:desde'],
        ];
    }
}
