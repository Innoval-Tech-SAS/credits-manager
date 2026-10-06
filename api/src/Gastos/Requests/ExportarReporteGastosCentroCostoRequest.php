<?php

namespace App\Gastos\Requests;

use App\Exportacion\Enums\FormatoExportacion;
use Illuminate\Validation\Rule;

class ExportarReporteGastosCentroCostoRequest extends ConsultarReporteGastosCentroCostoRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'formato' => ['required', Rule::enum(FormatoExportacion::class)],
        ];
    }
}
