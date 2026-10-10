<?php

namespace App\Conciliacion\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DesmarcarMarcaRequest extends FormRequest
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
