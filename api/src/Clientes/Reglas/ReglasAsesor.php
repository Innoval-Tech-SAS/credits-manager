<?php

namespace App\Clientes\Reglas;

use App\Usuarios\Constants\UsuarioConstants;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class ReglasAsesor
{
    /** Usuario activo de la empresa con rol asesor o administrador: el administrador recibe los clientes que nadie más atiende. */
    public static function asignable(int $empresaId): Exists
    {
        return Rule::exists('usuarios', 'id')->where(fn (Builder $consulta) => $consulta
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->whereNull('borrado_en')
            ->whereIn('rol_id', DB::table('roles')
                ->select('id')
                ->where('empresa_id', $empresaId)
                ->whereIn('nombre', UsuarioConstants::ROLES_ASIGNABLES)));
    }
}
