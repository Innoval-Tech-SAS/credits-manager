<?php

namespace App\Respaldos\Requests;

use App\Respaldos\Repositories\ConfiguracionRespaldoExternoRepository;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;

class ActualizarConfiguracionRespaldoExternoRequest extends FormRequest
{
    public function __construct(protected ConfiguracionRespaldoExternoRepository $configuraciones)
    {
        parent::__construct();
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'activa' => ['required', 'boolean'],
            'bucket' => ['nullable', 'string', 'max:190'],
            'region' => ['nullable', 'string', 'max:60'],
            'endpoint' => ['nullable', 'string', 'max:255', 'url'],
            'uso_path_style' => ['required', 'boolean'],
            'clave_acceso' => ['nullable', 'string', 'max:190'],
            'clave_secreta' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** Activar exige bucket y credenciales: la secreta puede venir de antes, sin reenviarla. */
    public function withValidator(ValidatorContract $validador): void
    {
        $validador->after(function (ValidatorContract $validador) {
            if (! $this->boolean('activa')) {
                return;
            }

            if (blank($this->input('bucket')) || blank($this->input('clave_acceso'))) {
                $validador->errors()->add('activa', __('validation.custom.respaldo_externo.datos_incompletos'));

                return;
            }

            $configuracion = $this->configuraciones->obtener();

            if (blank($this->input('clave_secreta')) && blank($configuracion->getRawOriginal('clave_secreta'))) {
                $validador->errors()->add('clave_secreta', __('validation.custom.respaldo_externo.datos_incompletos'));
            }
        });
    }
}
