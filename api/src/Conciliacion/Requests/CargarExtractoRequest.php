<?php

namespace App\Conciliacion\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CargarExtractoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'archivo' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
            // El archivo no trae año en sus fechas (solo "d/mm"), y puede cubrir varios meses: con el mes de
            // la primera fila basta para reconstruir el resto (ver el docblock de LectorExtractoBancolombia).
            'mes_primera_fila' => ['required', 'date_format:Y-m'],
        ];
    }
}
