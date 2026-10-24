<?php

namespace App\Gastos\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GuardarValorUvtRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'anio' => ['required', 'integer', 'min:2000', 'max:2100'],
            'valor' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
