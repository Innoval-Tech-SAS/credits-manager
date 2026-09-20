<?php

namespace App\Auditoria\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecuperarEventosAuditoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'todos' => ['nullable', 'boolean'],
            'evento_ids' => ['required_without:todos', 'array', 'min:1', 'max:500'],
            'evento_ids.*' => ['uuid'],
        ];
    }
}
