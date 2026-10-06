<?php

namespace App\Contabilidad\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Auxiliar por cuenta, por tercero o por cuenta y tercero. Hace falta al menos uno de los dos filtros. */
class ConsultarAuxiliarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $empresaId = $this->user()->empresa_id;

        return [
            'cuenta_id' => [
                'nullable', 'integer', 'required_without:tercero_id',
                Rule::exists('plan_cuentas', 'id')->where('empresa_id', $empresaId),
            ],
            'tercero_id' => [
                'nullable', 'integer', 'required_without:cuenta_id',
                Rule::exists('terceros', 'id')->where('empresa_id', $empresaId),
            ],
            'desde' => ['required', 'date_format:Y-m-d'],
            'hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ];
    }
}
