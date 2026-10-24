<?php

namespace App\Seguridad\Repositories;

use App\Seguridad\Models\SesionActiva;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * `sessions` no tiene `empresa_id` propio ni pasa por `EmpresaScope` (no hay sesión que lo aplique fuera
 * de una petición autenticada): el join con `usuarios` es la única forma de saber de qué empresa es cada
 * una, y es la excepción cruzada declarada para esta pantalla (solo superadmin, ver middleware de ruta).
 */
class SesionActivaRepository
{
    /** @return Collection<int, object{id:string, user_id:int, ip_address:?string, user_agent:?string, last_activity:int, usuario_nombre:string, usuario_login:string, es_superadmin:bool, empresa_id:?int, nombre_empresa:?string}> */
    public function listar(): Collection
    {
        return DB::table('sessions as s')
            ->join('usuarios as u', 'u.id', '=', 's.user_id')
            ->leftJoin('empresas as e', 'e.id', '=', 'u.empresa_id')
            ->whereNull('u.borrado_en')
            ->orderByDesc('s.last_activity')
            ->get([
                's.id', 's.user_id', 's.ip_address', 's.user_agent', 's.last_activity',
                'u.nombre as usuario_nombre', 'u.usuario as usuario_login', 'u.empresa_id',
                'e.nombre_empresa',
            ])
            ->map(function (object $fila) {
                $fila->es_superadmin = $fila->empresa_id === null;

                return $fila;
            });
    }

    /** @return bool si había una sesión con ese id */
    public function cerrar(string $id): bool
    {
        return (new SesionActiva)->newQuery()->whereKey($id)->delete() > 0;
    }

    public function cerrarDeEmpresa(int $empresaId): int
    {
        $usuarioIds = DB::table('usuarios')->where('empresa_id', $empresaId)->pluck('id');

        return (new SesionActiva)->newQuery()->whereIn('user_id', $usuarioIds)->delete();
    }
}
