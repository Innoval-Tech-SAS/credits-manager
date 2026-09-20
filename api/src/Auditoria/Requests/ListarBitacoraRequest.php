<?php

namespace App\Auditoria\Requests;

use App\Auditoria\Enums\AccionAuditoria;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListarBitacoraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'modulo' => ['nullable', 'string', 'max:50'],
            'accion' => ['nullable', Rule::enum(AccionAuditoria::class)],
            'autor' => ['nullable', 'string', 'max:255'],
            'entidad' => ['nullable', 'string', 'max:60'],
            'empresa_id' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
