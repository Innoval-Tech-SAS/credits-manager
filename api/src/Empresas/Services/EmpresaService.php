<?php

namespace App\Empresas\Services;

use App\Empresas\Constants\EmpresaConstants;
use App\Empresas\Models\Empresa;
use App\Empresas\Repositories\EmpresaRepository;
use App\Usuarios\Constants\UsuarioConstants;
use App\Usuarios\Models\Permiso;
use App\Usuarios\Models\Rol;
use App\Usuarios\Models\Usuario;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EmpresaService
{
    public function __construct(protected EmpresaRepository $empresas) {}

    /** Panel de super-admin: todas las empresas de la plataforma. */
    public function listarEmpresas(?string $busqueda, int $porPagina, int $pagina): LengthAwarePaginator
    {
        return $this->empresas->paginar($busqueda, max(1, min(100, $porPagina)), max(1, $pagina));
    }

    /** La empresa del usuario autenticado (autoservicio). */
    public function obtenerPropia(): Empresa
    {
        return $this->empresas->buscarPorIdOFallar(Auth::user()->empresa_id);
    }

    /** Crea una empresa nueva junto con su primer usuario admin y sus roles base. */
    public function crearEmpresaConAdmin(array $datos): Empresa
    {
        return DB::transaction(function () use ($datos) {
            $empresa = $this->empresas->crear([
                'nombre_empresa' => $datos['nombre_empresa'],
                'nit' => $datos['nit'],
                'direccion' => $datos['direccion'],
                'telefono' => $datos['telefono'],
                'correo' => $datos['correo'],
            ]);

            $rolAdmin = $this->crearRolesBase($empresa)[UsuarioConstants::ROL_ADMIN];

            Usuario::create([
                'empresa_id' => $empresa->id,
                'nombre' => $datos['admin_nombre'],
                'usuario' => strtolower($datos['admin_usuario']),
                'password_hash' => $datos['admin_password'],
                'rol_id' => $rolAdmin->id,
                'activo' => true,
            ]);

            return $empresa;
        });
    }

    public function editarEmpresa(Empresa $empresa, array $datos): Empresa
    {
        return $this->empresas->actualizar($empresa, $datos);
    }

    public function actualizarLogoPropia(UploadedFile $logo): Empresa
    {
        $empresa = $this->obtenerPropia();

        if ($empresa->logo_path) {
            Storage::disk('public')->delete(str_replace('storage/', '', $empresa->logo_path));
        }

        $path = $logo->store('logos', 'public');

        return $this->empresas->actualizar($empresa, ['logo_path' => 'storage/'.$path]);
    }

    /** @return array<string, Rol> roles creados, indexados por nombre */
    private function crearRolesBase(Empresa $empresa): array
    {
        $idPorClave = Permiso::query()->get(['id', 'modulo', 'accion'])
            ->mapWithKeys(fn (Permiso $permiso) => ["{$permiso->modulo}.{$permiso->accion}" => $permiso->id]);

        $roles = [];

        foreach (EmpresaConstants::ROLES_BASE as $nombre => $descripcion) {
            $rol = Rol::create([
                'empresa_id' => $empresa->id,
                'nombre' => $nombre,
                'descripcion' => $descripcion,
            ]);

            $clavesPermisos = $nombre === UsuarioConstants::ROL_ADMIN
                ? $idPorClave->keys()->all()
                : (EmpresaConstants::PERMISOS_POR_ROL[$nombre] ?? []);

            $rol->permisos()->sync(collect($clavesPermisos)->map(fn (string $clave) => $idPorClave[$clave])->all());

            $roles[$nombre] = $rol;
        }

        return $roles;
    }
}
