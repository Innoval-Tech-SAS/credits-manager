<?php

namespace App\Contabilidad\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportarMigracionAperturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::in(['plan_cuentas', 'terceros', 'saldos_iniciales'])],
            'archivo' => ['required', 'file', 'mimes:csv,txt'],
        ];
    }
}
