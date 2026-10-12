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

    public function esRolAsesor(int $rolId): bool
    {
        return $this->consulta()
            ->whereKey($rolId)
            ->where('nombre', UsuarioConstants::ROL_ASESOR)
            ->exists();
    }

    /**
     * Excepción deliberada al scope de empresa: el rol Super Admin no tiene empresa y lo piden contextos sin sesión
     * o con la sesión de otro usuario (seeder, tests). Solo lee esa fila; nunca un rol de empresa.
     */
    public function rolSuperAdmin(): Rol
    {
        return Rol::withoutGlobalScopes()
            ->whereNull('empresa_id')
            ->where('nombre', UsuarioConstants::ROL_SUPERADMIN)
            ->firstOrFail();
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
