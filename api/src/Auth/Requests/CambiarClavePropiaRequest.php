<?php

namespace App\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class CambiarClavePropiaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'clave_actual' => ['required', 'string'],
            'clave_nueva' => ['required', 'string', Password::min(8)->letters()->numbers()->symbols()],
        ];
    }
}
