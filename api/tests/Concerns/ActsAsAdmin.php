<?php

namespace Tests\Concerns;

use App\Empresas\Models\Empresa;
use App\Geografia\Models\Estado;
use App\Geografia\Models\Municipio;
use App\Geografia\Models\Pais;
use App\Usuarios\Constants\UsuarioConstants;
use App\Usuarios\Models\Rol;
use App\Usuarios\Models\Usuario;

/**
 * Setup mínimo para tests HTTP: cada llamada crea su propia empresa (ya no
 * hay "la" empresa única) con un rol y un usuario autenticado en sanctum.
 */
trait ActsAsAdmin
{
    protected function crearEmpresaDePrueba(array $overrides = []): Empresa
    {
        static $contador = 0;
        $contador++;

        return Empresa::create(array_merge([
            'nombre_empresa' => "Empresa de prueba {$contador}",
            'nit' => "90000000{$contador}-0",
            'direccion' => 'Calle falsa 123',
            'municipio_id' => $this->municipioDePrueba()->id,
            'telefono' => '3000000000',
            'correo' => "empresa{$contador}@example.com",
        ], $overrides));
    }

    /** Un único municipio compartido por todas las empresas de prueba: el catálogo es global. */
    protected function municipioDePrueba(): Municipio
    {
        $pais = Pais::firstOrCreate(['codigo' => 'CO'], ['nombre' => 'Colombia']);
        $estado = Estado::firstOrCreate(['pais_id' => $pais->id, 'codigo' => '47'], ['nombre' => 'Magdalena']);

        return Municipio::firstOrCreate(['estado_id' => $estado->id, 'codigo' => '47001'], ['nombre' => 'Santa Marta', 'es_capital' => true]);
    }

    protected function actingAsAdmin(?Empresa $empresa = null): Usuario
    {
        return $this->actingAsRol(UsuarioConstants::ROL_ADMIN, $empresa);
    }

    protected function actingAsAsesor(?Empresa $empresa = null): Usuario
    {
        return $this->actingAsRol('asesor', $empresa);
    }

    protected function actingAsSuperAdmin(): Usuario
    {
        $superAdmin = Usuario::create([
            'nombre' => 'Super-admin de prueba',
            'usuario' => 'superadmin_test_'.uniqid(),
            'password_hash' => 'irrelevante-en-tests',
            'activo' => true,
            'es_superadmin' => true,
        ]);

        $this->actingAs($superAdmin, 'sanctum');

        return $superAdmin;
    }

    /** Deshace actingAs*(): fuerza a que el siguiente request de prueba se resuelva sin usuario autenticado. */
    protected function salirDeSesion(): void
    {
        $this->app['auth']->forgetGuards();
    }

    private function actingAsRol(string $nombreRol, ?Empresa $empresa = null): Usuario
    {
        $empresa ??= $this->crearEmpresaDePrueba();

        $rol = Rol::create([
            'empresa_id' => $empresa->id,
            'nombre' => $nombreRol,
        ]);

        $usuario = Usuario::create([
            'empresa_id' => $empresa->id,
            'nombre' => ucfirst($nombreRol).' de prueba',
            'usuario' => $nombreRol.'_test_'.uniqid(),
            'password_hash' => 'irrelevante-en-tests',
            'rol_id' => $rol->id,
            'activo' => true,
        ]);

        $this->actingAs($usuario, 'sanctum');

        return $usuario;
    }
}
