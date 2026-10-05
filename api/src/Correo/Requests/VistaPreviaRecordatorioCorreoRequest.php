<?php

namespace App\Correo\Requests;

use App\Correo\Constants\CorreoConstants;
use App\Correo\Requests\Concerns\ValidaPlantillaCorreo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** Sin asunto ni cuerpo, la vista previa usa la plantilla guardada de la empresa (quien cobra no puede leer la configuración). */
class VistaPreviaRecordatorioCorreoRequest extends FormRequest
{
    use ValidaPlantillaCorreo;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cuota_id' => ['required', 'integer', 'min:1'],
            'asunto' => ['nullable', 'string', 'max:200'],
            'cuerpo' => ['nullable', 'string', 'max:'.CorreoConstants::MAXIMO_CARACTERES_CUERPO],
        ];
    }

    public function withValidator(Validator $validador): void
    {
        $this->validarVariablesDePlantilla($validador);
    }
}
