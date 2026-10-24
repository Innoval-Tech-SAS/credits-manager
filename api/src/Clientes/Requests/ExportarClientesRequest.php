<?php

namespace App\Clientes\Requests;

use App\Creditos\Enums\EstadoCredito;
use App\Exportacion\Enums\FormatoExportacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportarClientesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::in(['activo', 'inactivo'])],
            'estado_credito' => ['nullable', Rule::enum(EstadoCredito::class)],
            'formato' => ['required', Rule::enum(FormatoExportacion::class)],
        ];
    }
}
