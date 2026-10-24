<?php

namespace App\Auditoria\Repositories;

use App\Usuarios\Models\Usuario;
use Illuminate\Support\Collection;

/**
 * Catálogo de usuarios de todas las empresas para el filtro "Autor" de la bitácora: una excepción de
 * lectura cruzada, no la administración de usuarios (que sigue acotada a su empresa en `UsuarioController`).
 * Solo la usa el superadmin (middleware de ruta); un admin de empresa sigue usando `GET /usuarios`.
 */
class UsuarioAuditoriaRepository
{
    /** @return Collection<int, Usuario> cada fila trae además `nombre_empresa` (null para el superadmin) */
    public function listarTodos(): Collection
    {
        return Usuario::query()
            ->withoutGlobalScopes()
            ->leftJoin('empresas', 'empresas.id', '=', 'usuarios.empresa_id')
            ->whereNull('usuarios.borrado_en')
            ->orderBy('usuarios.nombre')
            ->get(['usuarios.id', 'usuarios.nombre', 'usuarios.usuario', 'empresas.nombre_empresa']);
    }
}
