<?php

namespace App\Correo\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EnviarPruebaCorreoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'destinatario' => ['required', 'string', 'email:rfc', 'max:190'],
        ];
    }
}
