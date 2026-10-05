<?php

namespace App\Cartera\Requests;

use App\Exportacion\Enums\FormatoExportacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportarCarteraRequest extends FormRequest
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
