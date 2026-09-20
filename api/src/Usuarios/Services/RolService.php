<?php

namespace App\Usuarios\Services;

use App\Usuarios\Constants\UsuarioConstants;
use App\Usuarios\Models\Rol;
use App\Usuarios\Repositories\RolRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RolService
{
    public function __construct(protected RolRepository $roles) {}

    public function paginar(?string $busqueda, int $porPagina, int $pagina): LengthAwarePaginator
    {
        return $this->roles->paginar($busqueda, max(1, min(100, $porPagina)), max(1, $pagina));
    }

    public function crear(array $datos): Rol
    {
        return DB::transaction(function () use ($datos) {
            $rol = $this->roles->crear([
                'nombre' => $datos['nombre'],
                'descripcion' => $datos['descripcion'] ?? null,
            ]);

            $this->roles->sincronizarPermisos($rol, $datos['permisos'] ?? []);

            return $rol;
        });
    }

    public function editar(Rol $rol, array $datos): Rol
    {
        if ($rol->nombre === UsuarioConstants::ROL_ADMIN && $datos['nombre'] !== UsuarioConstants::ROL_ADMIN) {
            throw ValidationException::withMessages([
                'nombre' => __('validation.custom.rol.no_renombrar_admin'),
            ]);
        }

        return DB::transaction(function () use ($rol, $datos) {
            $rol = $this->roles->actualizar($rol, [
                'nombre' => $datos['nombre'],
                'descripcion' => $datos['descripcion'] ?? null,
            ]);

            $this->roles->sincronizarPermisos($rol, $datos['permisos'] ?? []);

            return $rol;
        });
    }

    public function eliminar(Rol $rol): bool
    {
        if ($rol->nombre === UsuarioConstants::ROL_ADMIN) {
            throw ValidationException::withMessages([
                'rol' => __('validation.custom.rol.no_eliminar_admin'),
            ]);
        }

        if ($this->roles->tieneUsuariosActivos($rol->id)) {
            throw ValidationException::withMessages([
                'rol' => __('validation.custom.rol.tiene_usuarios_activos'),
            ]);
        }

        return $this->roles->eliminar($rol);
    }
}
