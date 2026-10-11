<?php

namespace App\Gastos\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EditarValorUvtRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'valor' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
