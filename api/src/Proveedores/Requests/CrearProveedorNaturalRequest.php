<?php

namespace App\Proveedores\Requests;

use App\Terceros\Reglas\ReglasPersona;
use App\Terceros\Requests\Concerns\ValidaDocumentoNoRegistrado;
use Illuminate\Foundation\Http\FormRequest;

class CrearProveedorNaturalRequest extends FormRequest
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
            ...ReglasPersona::contacto($this->user()->empresa_id),
        ];
    }

    protected function tipoDocumentoDelRegistro(): ?string
    {
        return $this->input('tipo_documento');
    }

    protected function extensionAValidar(): string
    {
        return 'proveedor';
    }

    protected function claveMensajeDuplicado(): string
    {
        return 'ya_es_proveedor';
    }
}
