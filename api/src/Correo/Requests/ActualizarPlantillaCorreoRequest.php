<?php

namespace App\Correo\Requests;

use App\Correo\Constants\CorreoConstants;
use App\Correo\Requests\Concerns\ValidaPlantillaCorreo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** El tipo es inmutable: no viaja en el body, se toma de la plantilla ya creada para validar las variables. */
class ActualizarPlantillaCorreoRequest extends FormRequest
{
    use ValidaPlantillaCorreo;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:120'],
            'asunto' => ['required', 'string', 'max:200'],
            'cuerpo' => ['required', 'string', 'max:'.CorreoConstants::MAXIMO_CARACTERES_CUERPO],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['tipo' => $this->route('plantilla')?->tipo?->value]);
    }

    public function withValidator(Validator $validador): void
    {
        $this->validarVariablesDePlantilla($validador);
    }
}
