<?php

namespace App\Auditoria\Requests;

use App\Exportacion\Enums\FormatoExportacion;
use Illuminate\Validation\Rule;

class ExportarBitacoraRequest extends ListarBitacoraRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'formato' => ['required', Rule::enum(FormatoExportacion::class)],
        ];
    }
}
