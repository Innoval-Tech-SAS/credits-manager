<?php

namespace App\Usuarios\Services;

use App\Usuarios\Constants\UsuarioConstants;
use App\Usuarios\Models\Usuario;
use App\Usuarios\Repositories\RolRepository;
use App\Usuarios\Repositories\UsuarioRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UsuarioService
{
    public function __construct(
        protected UsuarioRepository $usuarios,
        protected RolRepository $roles,
    ) {}

    public function listarUsuarios(?string $busqueda, string $columna, string $direccion, int $porPagina, int $pagina): LengthAwarePaginator
    {
        $columnaValida = in_array($columna, UsuarioConstants::COLUMNAS_ORDENABLES, true) ? $columna : null;
        $direccionValida = $direccion === 'desc' ? 'desc' : 'asc';

        return $this->usuarios->paginar(
            busqueda: $busqueda,
            columna: $columnaValida,
            direccion: $direccionValida,
            porPagina: max(1, min(100, $porPagina)),
            pagina: max(1, $pagina),
        );
    }

    public function crearUsuario(array $datos): Usuario
    {
        return DB::transaction(function () use ($datos) {
            $usuario = $this->usuarios->crear([
                'nombre' => $datos['nombre'],
                'usuario' => $datos['usuario'],
                'password_hash' => $datos['clave'],
                'rol_id' => $datos['rol_id'],
                'activo' => $datos['activo'],
            ]);

            $this->usuarios->sincronizarPermisosExtra($usuario, $this->mapaPermisosExtra($datos));

            return $usuario;
        });
    }

    public function editarUsuario(Usuario $usuario, array $datos): Usuario
    {
        $this->impedirAutoBloqueoDeAdmin($usuario, $datos['rol_id'], $datos['activo']);

        return DB::transaction(function () use ($usuario, $datos) {
            $usuario = $this->usuarios->actualizar($usuario, [
                'nombre' => $datos['nombre'],
                'usuario' => $datos['usuario'],
                'rol_id' => $datos['rol_id'],
                'activo' => $datos['activo'],
            ]);

            $this->usuarios->sincronizarPermisosExtra($usuario, $this->mapaPermisosExtra($datos));

            return $usuario;
        });
    }

    private function mapaPermisosExtra(array $datos): array
    {
        return collect($datos['permisos_extra'] ?? [])
            ->mapWithKeys(fn (array $excepcion) => [
                $excepcion['permiso_id'] => ['concedido' => $excepcion['concedido']],
            ])
            ->all();
    }

    public function cambiarClave(Usuario $usuario, string $claveNueva): Usuario
    {
        return $this->usuarios->actualizar($usuario, ['password_hash' => $claveNueva]);
    }

    public function eliminarUsuario(Usuario $usuario): bool
    {
        $this->impedirAutoBloqueoDeAdmin($usuario, $usuario->rol_id, false);

        return DB::transaction(function () use ($usuario) {
            $this->usuarios->actualizar($usuario, ['activo' => false]);

            return $this->usuarios->eliminar($usuario);
        });
    }

    /**
     * Replica la protección de usuarios/guardar.php: un admin no puede quitarse
     * a sí mismo el rol de admin ni desactivarse si es el último admin activo.
     */
    private function impedirAutoBloqueoDeAdmin(Usuario $usuario, int $nuevoRolId, bool $nuevoActivo): void
    {
        $esElUsuarioAutenticado = Auth::id() === $usuario->id;
        $perderiaAdmin = ! $this->roles->esRolAdmin($nuevoRolId) || ! $nuevoActivo;

        if (! $esElUsuarioAutenticado || ! $perderiaAdmin) {
            return;
        }

        if ($this->usuarios->contarAdminsActivos() <= 1) {
            throw ValidationException::withMessages([
                'rol_id' => __('validation.custom.usuario.unico_admin_activo'),
            ]);
        }
    }
}
