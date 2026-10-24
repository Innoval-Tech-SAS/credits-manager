<?php

namespace App\Usuarios\Requests\Concerns;

use App\Usuarios\Repositories\RolRepository;
use Illuminate\Validation\Validator;

/** El alcance global solo existe para el rol asesor; la base lo rechaza igual, aquí se responde 422 con el motivo. */
trait ValidaAlcanceAsesor
{
    protected function validarAlcanceAsesor(Validator $validador): void
    {
        $validador->after(function (Validator $validador) {
            if ($validador->errors()->hasAny(['rol_id', 'es_asesor_global']) || ! $this->boolean('es_asesor_global')) {
                return;
            }

            if (! app(RolRepository::class)->esRolAsesor($this->integer('rol_id'))) {
                $validador->errors()->add('es_asesor_global', __('validation.custom.usuario.alcance_global_solo_asesor'));
            }
        });
    }
}
