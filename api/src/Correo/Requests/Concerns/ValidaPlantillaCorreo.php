<?php

namespace App\Correo\Requests\Concerns;

use App\Correo\Services\PlantillaCorreoService;
use Illuminate\Validation\Validator;

/** Rechaza variables que no existen (`{clente}`) en vez de enviar un correo con llaves a la vista. */
trait ValidaPlantillaCorreo
{
    protected function validarVariablesDePlantilla(Validator $validador): void
    {
        $validador->after(function (Validator $validador) {
            foreach (['plantilla_asunto', 'plantilla_cuerpo', 'asunto', 'cuerpo'] as $campo) {
                $texto = $this->input($campo);

                if (! is_string($texto)) {
                    continue;
                }

                $desconocidas = PlantillaCorreoService::variablesDesconocidas($texto);

                if ($desconocidas !== []) {
                    $validador->errors()->add($campo, __('validation.custom.correo.variable_desconocida', [
                        'variables' => implode(', ', array_map(fn (string $variable) => '{'.$variable.'}', $desconocidas)),
                    ]));
                }
            }
        });
    }
}
