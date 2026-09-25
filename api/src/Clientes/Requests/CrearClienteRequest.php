<?php

namespace App\Clientes\Requests;

use App\Terceros\Reglas\ReglasPersona;
use App\Terceros\Requests\Concerns\ValidaDocumentoNoRegistrado;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Los clientes de crédito son siempre personas naturales (`ClienteConstants::TIPO_PERSONA`). */
class CrearClienteRequest extends FormRequest
{
    use ValidaDocumentoNoRegistrado;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...ReglasPersona::natural(),
            ...ReglasPersona::contacto($this->user()->empresa_id, obligatorio: true),
            'empleador_id' => [
                'required', 'integer',
                Rule::exists('empleadores', 'id')->where('empresa_id', $this->user()->empresa_id),
            ],
        ];
    }

    protected function tipoDocumentoDelRegistro(): ?string
    {
        return $this->input('tipo_documento');
    }

    protected function extensionAValidar(): string
    {
        return 'cliente';
    }

    protected function claveMensajeDuplicado(): string
    {
        return 'ya_es_cliente';
    }
}
