<?php

namespace App\Pagos\Requests;

use App\Exportacion\Enums\FormatoExportacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportarPagosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'mes' => ['nullable', 'date_format:Y-m'],
            'formato' => ['required', Rule::enum(FormatoExportacion::class)],
        ];
    }
}
