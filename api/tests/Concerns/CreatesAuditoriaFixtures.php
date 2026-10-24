<?php

namespace Tests\Concerns;

use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Models\BitacoraAuditoria;
use App\Empresas\Models\Empresa;
use App\Usuarios\Models\Permiso;
use App\Usuarios\Models\Rol;
use App\Usuarios\Models\Usuario;
use Illuminate\Support\Str;

/** Requiere `ActsAsAdmin` (crearEmpresaDePrueba). */
trait CreatesAuditoriaFixtures
{
    protected function crearEventoBitacora(array $atributos = []): BitacoraAuditoria
    {
        return BitacoraAuditoria::query()->create(array_merge([
            'evento_id' => (string) Str::uuid(),
            'empresa_id' => null,
            'usuario_id' => null,
            'autor_nombre' => 'Ana Pérez',
            'autor_usuario' => 'ana',
            'autor_rol' => 'admin',
            'es_superadmin' => false,
            'modulo' => 'clientes',
            'accion' => AccionAuditoria::Crear,
            'descripcion' => 'Cliente creado',
            'fecha_hora' => now(),
        ], $atributos));
    }

    /** Usuario de un rol con exactamente los permisos indicados ("modulo.accion"), autenticado en sanctum. */
    protected function actingAsRolConPermisos(string $nombreRol, array $permisos, ?Empresa $empresa = null): Usuario
    {
        $empresa ??= $this->crearEmpresaDePrueba();

        $rol = Rol::create(['empresa_id' => $empresa->id, 'nombre' => $nombreRol]);

        $ids = collect($permisos)->map(function (string $clave) {
            [$modulo, $accion] = explode('.', $clave);

            return Permiso::query()->firstOrCreate(['modulo' => $modulo, 'accion' => $accion])->id;
        });

        $rol->permisos()->sync($ids->all());

        $usuario = Usuario::create([
            'empresa_id' => $empresa->id,
            'nombre' => ucfirst($nombreRol).' con permisos',
            'usuario' => $nombreRol.'_perm_'.uniqid(),
            'password_hash' => 'irrelevante-en-tests',
            'rol_id' => $rol->id,
            'activo' => true,
        ]);

        $this->actingAs($usuario, 'sanctum');

        return $usuario;
    }
}
