<?php

namespace App\Egresos\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReversarEgresoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }
}
