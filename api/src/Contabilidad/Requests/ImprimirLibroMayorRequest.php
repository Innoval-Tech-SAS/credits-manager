<?php

namespace App\Contabilidad\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImprimirLibroMayorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cuenta_id' => ['required', 'integer', Rule::exists('plan_cuentas', 'id')->where('empresa_id', $this->user()->empresa_id)],
            'desde' => ['required', 'date_format:Y-m-d'],
            'hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ];
    }
}
