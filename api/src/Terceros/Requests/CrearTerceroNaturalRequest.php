<?php

namespace App\Terceros\Requests;

use App\Terceros\Reglas\ReglasPersona;
use App\Terceros\Requests\Concerns\ValidaDocumentoNoRegistrado;
use Illuminate\Foundation\Http\FormRequest;

class CrearTerceroNaturalRequest extends FormRequest
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
        return 'otroTercero';
    }

    protected function claveMensajeDuplicado(): string
    {
        return 'ya_registrado';
    }
}
