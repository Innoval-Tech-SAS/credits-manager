<?php

namespace App\Geografia\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListarEstadosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pais_id' => ['required', 'integer', Rule::exists('paises', 'id')->whereNull('borrado_en')],
        ];
    }
}
