<?php

namespace App\Proveedores\Requests;

use App\Terceros\Enums\TipoDocumentoTercero;
use App\Terceros\Reglas\ReglasPersona;
use App\Terceros\Requests\Concerns\ValidaDocumentoNoRegistrado;
use Illuminate\Foundation\Http\FormRequest;

class CrearProveedorJuridicaRequest extends FormRequest
{
    use ValidaDocumentoNoRegistrado;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...ReglasPersona::juridica(),
            ...ReglasPersona::contacto($this->user()->empresa_id),
        ];
    }

    protected function tipoDocumentoDelRegistro(): ?string
    {
        return TipoDocumentoTercero::Nit->value;
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
