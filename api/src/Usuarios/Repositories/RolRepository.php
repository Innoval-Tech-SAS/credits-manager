<?php

namespace App\Usuarios\Repositories;

use App\Core\Base\BaseRepository;
use App\Usuarios\Constants\UsuarioConstants;
use App\Usuarios\Models\Rol;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class RolRepository extends BaseRepository
{
    public function __construct(Rol $rol)
    {
        parent::__construct($rol);
    }

    public function paginar(?string $busqueda, int $porPagina, int $pagina): LengthAwarePaginator
    {
        $consulta = $this->consulta()->with('permisos')->orderBy('nombre');

        if ($busqueda) {
            $consulta->where(function (Builder $subconsulta) use ($busqueda) {
                $subconsulta->where('nombre', 'like', "%{$busqueda}%")
                    ->orWhere('descripcion', 'like', "%{$busqueda}%");
            });
        }

        return $consulta->paginate(perPage: $porPagina, page: $pagina);
    }

    public function esRolAdmin(int $rolId): bool
    {
        return $this->consulta()
            ->whereKey($rolId)
            ->where('nombre', UsuarioConstants::ROL_ADMIN)
            ->exists();
    }

    public function sincronizarPermisos(Rol $rol, array $permisoIds): void
    {
        $rol->permisos()->sync($permisoIds);
    }

    public function tieneUsuariosActivos(int $rolId): bool
    {
        return $this->buscarPorIdOFallar($rolId)
            ->usuarios()
            ->where('activo', true)
            ->exists();
    }
}
