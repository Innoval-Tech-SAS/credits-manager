<?php

namespace App\Usuarios\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Services\EmisorAuditoria;
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
        protected EmisorAuditoria $auditoria,
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

            $this->auditoria->registrarCreacion(AuditoriaConstants::MODULO_USUARIOS, "Usuario {$usuario->usuario} creado", $usuario, ['permisos_extra' => $this->permisosExtraDe($usuario)]);

            return $usuario;
        });
    }

    public function editarUsuario(Usuario $usuario, array $datos): Usuario
    {
        $this->impedirAutoBloqueoDeAdmin($usuario, $datos['rol_id'], $datos['activo']);

        return DB::transaction(function () use ($usuario, $datos) {
            $anteriores = $this->auditoria->instantanea($usuario);
            $permisosAnteriores = $this->permisosExtraDe($usuario);

            $usuario = $this->usuarios->actualizar($usuario, [
                'nombre' => $datos['nombre'],
                'usuario' => $datos['usuario'],
                'rol_id' => $datos['rol_id'],
                'activo' => $datos['activo'],
            ]);

            $this->usuarios->sincronizarPermisosExtra($usuario, $this->mapaPermisosExtra($datos));
            $permisosNuevos = $this->permisosExtraDe($usuario);

            $this->auditoria->registrarModificacion(AuditoriaConstants::MODULO_USUARIOS, "Usuario {$usuario->usuario} modificado", $usuario, $anteriores);

            if ($permisosAnteriores !== $permisosNuevos || (int) $anteriores['rol_id'] !== (int) $usuario->rol_id) {
                $this->auditoria->registrar(
                    AccionAuditoria::CambioPermisos,
                    AuditoriaConstants::MODULO_USUARIOS,
                    "Permisos de {$usuario->usuario} modificados",
                    'usuario',
                    $usuario->id,
                    ['rol_id' => $anteriores['rol_id'], 'permisos_extra' => $permisosAnteriores],
                    ['rol_id' => $usuario->rol_id, 'permisos_extra' => $permisosNuevos],
                );
            }

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

    /** @return array<string, bool> "modulo.accion" => concedido, ordenado para poder comparar antes/después */
    private function permisosExtraDe(Usuario $usuario): array
    {
        $permisos = $usuario->permisosExtra()->get()
            ->mapWithKeys(fn ($permiso) => ["{$permiso->modulo}.{$permiso->accion}" => (bool) $permiso->pivot->concedido])
            ->all();

        ksort($permisos);

        return $permisos;
    }

    public function cambiarClave(Usuario $usuario, string $claveNueva): Usuario
    {
        $usuario = $this->usuarios->actualizar($usuario, ['password_hash' => $claveNueva]);

        $this->auditoria->registrar(AccionAuditoria::Modificar, AuditoriaConstants::MODULO_USUARIOS, "Contraseña de {$usuario->usuario} restablecida por el administrador", 'usuario', $usuario->id);

        return $usuario;
    }

    public function eliminarUsuario(Usuario $usuario): bool
    {
        $this->impedirAutoBloqueoDeAdmin($usuario, $usuario->rol_id, false);

        return DB::transaction(function () use ($usuario) {
            $this->usuarios->actualizar($usuario, ['activo' => false]);

            $eliminado = $this->usuarios->eliminar($usuario);

            $this->auditoria->registrarEliminacion(AuditoriaConstants::MODULO_USUARIOS, "Usuario {$usuario->usuario} eliminado", $usuario);

            return $eliminado;
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
