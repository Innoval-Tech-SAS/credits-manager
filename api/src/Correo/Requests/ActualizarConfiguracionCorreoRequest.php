<?php

namespace App\Correo\Requests;

use App\Correo\Constants\CorreoConstants;
use App\Correo\Enums\CifradoCorreo;
use App\Correo\Enums\DriverCorreo;
use App\Correo\Requests\Concerns\ValidaPlantillaCorreo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ActualizarConfiguracionCorreoRequest extends FormRequest
{
    use ValidaPlantillaCorreo;

    public function authorize(): bool
    {
        return true;
    }

    /** La clave se omite para conservar la guardada; enviarla vacía no la borra. */
    public function rules(): array
    {
        return [
            'remitente' => ['required', 'string', 'email:rfc', 'max:190'],
            'nombre_remitente' => ['required', 'string', 'max:120'],
            'driver' => ['required', Rule::enum(DriverCorreo::class)],
            'host' => ['required_if:driver,smtp', 'string', 'max:190'],
            'puerto' => ['required_if:driver,smtp', 'integer', 'between:1,65535'],
            'cifrado' => ['required', Rule::enum(CifradoCorreo::class)],
            'usuario' => ['nullable', 'string', 'max:190'],
            'clave' => ['nullable', 'string', 'max:255'],
            'plantilla_asunto' => ['required', 'string', 'max:200'],
            'plantilla_cuerpo' => ['required', 'string', 'max:'.CorreoConstants::MAXIMO_CARACTERES_CUERPO],
        ];
    }

    public function withValidator(Validator $validador): void
    {
        $this->validarVariablesDePlantilla($validador);
    }
}
