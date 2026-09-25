<?php

namespace App\Proveedores\Requests;

use App\Terceros\Enums\TipoDocumentoTercero;
use App\Terceros\Reglas\ReglasPersona;
use Illuminate\Foundation\Http\FormRequest;

class EditarProveedorJuridicaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $reglas = ReglasPersona::juridica('required');
        $reglas['numero_documento'][] = ReglasPersona::documentoUnico($this->user()->empresa_id, TipoDocumentoTercero::Nit->value, $this->route('proveedor')->tercero_id);

        return [
            ...$reglas,
            ...ReglasPersona::contacto($this->user()->empresa_id),
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
