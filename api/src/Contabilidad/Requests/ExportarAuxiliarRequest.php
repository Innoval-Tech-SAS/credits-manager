<?php

namespace App\Contabilidad\Requests;

use App\Exportacion\Enums\FormatoExportacion;
use Illuminate\Validation\Rule;

class ExportarAuxiliarRequest extends ConsultarAuxiliarRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'formato' => ['required', Rule::enum(FormatoExportacion::class)],
        ];
    }
}
