<?php

namespace App\Correo\Requests;

use App\Correo\Enums\CifradoCorreo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActualizarConfiguracionCorreoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** La clave se omite para conservar la guardada; enviarla vacía no la borra. */
    public function rules(): array
    {
        return [
            'remitente' => ['required', 'string', 'email:rfc', 'max:190'],
            'nombre_remitente' => ['required', 'string', 'max:120'],
            'host' => ['required', 'string', 'max:190'],
            'puerto' => ['required', 'integer', 'between:1,65535'],
            'cifrado' => ['required', Rule::enum(CifradoCorreo::class)],
            'usuario' => ['nullable', 'string', 'max:190'],
            'clave' => ['nullable', 'string', 'max:255'],
        ];
    }
}
