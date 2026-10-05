<?php

namespace App\Creditos\Requests;

use App\Exportacion\Enums\FormatoExportacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportarEstadoCuentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'formato' => ['required', Rule::enum(FormatoExportacion::class)],
        ];
    }
}
