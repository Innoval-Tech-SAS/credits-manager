<?php

namespace App\Proveedores\Requests;

use App\Terceros\Reglas\ReglasPersona;
use Illuminate\Foundation\Http\FormRequest;

class EditarProveedorNaturalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $reglas = ReglasPersona::natural('required');
        $reglas['numero_documento'][] = ReglasPersona::documentoUnico($this->user()->empresa_id, $this->input('tipo_documento'), $this->route('proveedor')->tercero_id);

        return [
            ...$reglas,
            ...ReglasPersona::contacto($this->user()->empresa_id),
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
