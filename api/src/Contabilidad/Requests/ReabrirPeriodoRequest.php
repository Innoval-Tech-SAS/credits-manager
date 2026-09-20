<?php

namespace App\Contabilidad\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReabrirPeriodoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'max:500'],
        ];
    }
}
