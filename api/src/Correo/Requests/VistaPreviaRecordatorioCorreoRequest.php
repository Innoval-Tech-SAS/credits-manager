<?php

namespace App\Correo\Requests;

use App\Correo\Enums\TipoCorreo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Sin plantilla explícita, la vista previa usa la predeterminada de cobranza de la empresa. */
class VistaPreviaRecordatorioCorreoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cuota_id' => ['required', 'integer', 'min:1'],
            'plantilla_correo_id' => [
                'nullable',
                'integer',
                Rule::exists('plantillas_correo', 'id')
                    ->where('empresa_id', $this->user()->empresa_id)
                    ->where('tipo', TipoCorreo::Cobranza->value),
            ],
        ];
    }
}
