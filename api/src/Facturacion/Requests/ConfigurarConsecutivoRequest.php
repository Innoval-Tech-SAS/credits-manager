<?php

namespace App\Facturacion\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfigurarConsecutivoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prefijo' => ['nullable', 'string', 'max:10', 'regex:/^[A-Za-z0-9]*$/'],
            'numero_inicial' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
