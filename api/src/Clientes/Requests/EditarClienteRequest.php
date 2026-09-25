<?php

namespace App\Clientes\Requests;

use App\Terceros\Reglas\ReglasPersona;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EditarClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $reglas = ReglasPersona::natural();
        $reglas['numero_documento'][] = ReglasPersona::documentoUnico($this->user()->empresa_id, (string) $this->input('tipo_documento'), $this->route('cliente')->tercero_id);

        return [
            ...$reglas,
            ...ReglasPersona::contacto($this->user()->empresa_id, obligatorio: true),
            'empleador_id' => [
                'required', 'integer',
                Rule::exists('empleadores', 'id')->where('empresa_id', $this->user()->empresa_id),
            ],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
