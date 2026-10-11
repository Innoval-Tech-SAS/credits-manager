<?php

namespace App\Correo\Requests;

use App\Correo\Constants\CorreoConstants;
use App\Correo\Enums\TipoCorreo;
use App\Correo\Requests\Concerns\ValidaPlantillaCorreo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CrearPlantillaCorreoRequest extends FormRequest
{
    use ValidaPlantillaCorreo;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::enum(TipoCorreo::class)],
            'nombre' => ['required', 'string', 'max:120'],
            'asunto' => ['required', 'string', 'max:200'],
            'cuerpo' => ['required', 'string', 'max:'.CorreoConstants::MAXIMO_CARACTERES_CUERPO],
        ];
    }

    public function withValidator(Validator $validador): void
    {
        $this->validarVariablesDePlantilla($validador);
    }
}
