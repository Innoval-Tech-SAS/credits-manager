<?php

namespace App\Auditoria\Requests;

use App\Auditoria\Services\RecuperacionAuditoriaService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListarRespaldoAuditoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'estado' => ['nullable', Rule::in([
                RecuperacionAuditoriaService::PENDIENTE,
                RecuperacionAuditoriaService::RECUPERADO,
                RecuperacionAuditoriaService::NO_RECUPERABLE,
            ])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
