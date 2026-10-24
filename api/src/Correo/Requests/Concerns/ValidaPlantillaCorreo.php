<?php

namespace App\Correo\Requests\Concerns;

use App\Correo\Enums\TipoCorreo;
use App\Correo\Services\PlantillaCorreoService;
use Illuminate\Validation\Validator;

/** Rechaza variables que no existen (`{clente}`) en vez de enviar un correo con llaves a la vista. */
trait ValidaPlantillaCorreo
{
    protected function validarVariablesDePlantilla(Validator $validador): void
    {
        $validador->after(function (Validator $validador) {
            $tipo = TipoCorreo::tryFrom((string) $this->input('tipo'));

            if ($tipo === null) {
                return;
            }

            foreach (['asunto', 'cuerpo'] as $campo) {
                $texto = $this->input($campo);

                if (! is_string($texto)) {
                    continue;
                }

                $desconocidas = PlantillaCorreoService::variablesDesconocidas($texto, $tipo);

                if ($desconocidas !== []) {
                    $validador->errors()->add($campo, __('validation.custom.correo.variable_desconocida', [
                        'variables' => implode(', ', array_map(fn (string $variable) => '{'.$variable.'}', $desconocidas)),
                    ]));
                }
            }
        });
    }
}
