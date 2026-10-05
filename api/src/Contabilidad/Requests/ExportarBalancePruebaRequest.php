<?php

namespace App\Contabilidad\Requests;

use App\Exportacion\Enums\FormatoExportacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportarBalancePruebaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hasta' => ['required', 'date_format:Y-m-d'],
            'formato' => ['required', Rule::enum(FormatoExportacion::class)],
        ];
    }
}
