<?php

namespace App\Cartera\Requests;

use App\Exportacion\Enums\FormatoExportacion;
use Illuminate\Validation\Rule;

class ExportarReporteEmpleadorRequest extends DescargarReporteEmpleadorRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'formato' => ['required', Rule::enum(FormatoExportacion::class)],
        ];
    }
}
