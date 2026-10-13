<?php

namespace App\Monitoreo\Requests;

use App\Monitoreo\Constants\MonitoreoConstants;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListarTrabajosFallidosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cola' => ['nullable', Rule::in(MonitoreoConstants::COLAS)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
