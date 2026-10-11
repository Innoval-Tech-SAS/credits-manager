<?php

namespace Tests\Feature\Empresas;

use App\Empresas\Constants\EmpresaConstants;
use App\Usuarios\Models\Permiso;
use App\Usuarios\Models\Rol;
use App\Usuarios\Models\Usuario;
use Database\Seeders\PermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesAuditoriaFixtures;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\TestCase;

/** El anexo solo le da al asesor crear clientes, consultar lo asignado e imprimir; DDA confirmó que pagos y créditos los crea cartera o admin. */
class PermisosAsesorTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesAuditoriaFixtures;
    use CreatesCreditFixtures;
    use RefreshDatabase;

    private const REVOCADOS = [
        ['modulo' => 'clientes', 'accion' => 'editar'],
        ['modulo' => 'creditos', 'accion' => 'crear'],
        ['modulo' => 'pagos', 'accion' => 'crear'],
    ];

    /** @return list<string> claves "modulo.accion" que tiene el rol dado de una empresa */
    private function permisosDelRol(int $empresaId, string $rol): array
    {
        return DB::table('rol_permiso')
            ->join('roles', 'roles.id', '=', 'rol_permiso.rol_id')
            ->join('permisos', 'permisos.id', '=', 'rol_permiso.permiso_id')
            ->where('roles.empresa_id', $empresaId)
            ->where('roles.nombre', $rol)
            ->orderBy('permisos.modulo')->orderBy('permisos.accion')
            ->get(['permisos.modulo', 'permisos.accion'])
            ->map(fn ($permiso) => "{$permiso->modulo}.{$permiso->accion}")->all();
    }

    public function test_el_catalogo_del_asesor_ya_no_incluye_editar_clientes_ni_crear_creditos_o_pagos(): void
    {
        $catalogo = EmpresaConstants::PERMISOS_POR_ROL['asesor'];

        foreach (self::REVOCADOS as $permiso) {
            $this->assertNotContains("{$permiso['modulo']}.{$permiso['accion']}", $catalogo);
        }
        $this->assertContains('clientes.crear', $catalogo, 'El anexo sí le da crear clientes al asesor.');
    }

    public function test_una_empresa_nueva_no_asigna_al_asesor_editar_clientes_ni_crear_creditos_o_pagos(): void
    {
        $this->seed(PermisoSeeder::class);
        $this->actingAsSuperAdmin();

        $empresaId = $this->postJson('/api/v1/empresas', [
            'nombre_empresa' => 'Empresa asesora SAS', 'nit' => '900888777-1', 'digito_verificacion' => '1', 'direccion' => 'Calle 1',
            'municipio_id' => $this->municipioDePrueba()->id, 'telefono' => '3001112222',
            'correo' => 'asesora@example.com', 'admin_nombre' => 'Admin', 'admin_usuario' => 'admin_asesora', 'admin_password' => 'Clave123!',
        ])->assertCreated()->json('empresa.id');

        $permisosAsesor = $this->permisosDelRol($empresaId, 'asesor');

        foreach (self::REVOCADOS as $permiso) {
            $this->assertNotContains("{$permiso['modulo']}.{$permiso['accion']}", $permisosAsesor);
        }
        $this->assertEqualsCanonicalizing(EmpresaConstants::PERMISOS_POR_ROL['asesor'], $permisosAsesor, 'El rol creado debe calcar exactamente el catálogo vigente.');
    }

    public function test_la_migracion_revoca_los_permisos_en_empresas_existentes_sin_duplicar_y_se_revierte(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $asesor = Rol::create(['empresa_id' => $empresa->id, 'nombre' => 'asesor']);
        $asesor->permisos()->sync(collect([
            ['modulo' => 'clientes', 'accion' => 'listar'],
            ['modulo' => 'clientes', 'accion' => 'editar'],
            ['modulo' => 'creditos', 'accion' => 'crear'],
            ['modulo' => 'pagos', 'accion' => 'crear'],
        ])->map(fn (array $clave) => Permiso::firstOrCreate($clave)->id));

        $migracion = require database_path('migrations/2026_10_01_100000_quitar_permisos_edicion_y_creacion_del_asesor.php');

        $migracion->up();
        $migracion->up();

        $permisos = $this->permisosDelRol($empresa->id, 'asesor');
        $this->assertSame(['clientes.listar'], $permisos, 'Solo debía quedar el permiso que no se revoca.');

        $migracion->down();

        $this->assertSame(
            ['clientes.editar', 'clientes.listar', 'creditos.crear', 'pagos.crear'],
            $this->permisosDelRol($empresa->id, 'asesor'),
        );
    }

    public function test_la_migracion_no_toca_excepciones_personalizadas_por_usuario(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $asesor = Rol::create(['empresa_id' => $empresa->id, 'nombre' => 'asesor']);
        $asesor->permisos()->sync(collect(self::REVOCADOS)->map(fn (array $clave) => Permiso::firstOrCreate($clave)->id));

        $usuario = Usuario::create([
            'empresa_id' => $empresa->id, 'nombre' => 'Asesor con excepción', 'usuario' => 'asesor_excepcion_'.uniqid(),
            'password_hash' => 'irrelevante-en-tests', 'rol_id' => $asesor->id, 'activo' => true,
        ]);
        $usuario->permisosExtra()->attach(Permiso::firstOrCreate(['modulo' => 'pagos', 'accion' => 'crear'])->id, ['concedido' => true]);

        $migracion = require database_path('migrations/2026_10_01_100000_quitar_permisos_edicion_y_creacion_del_asesor.php');
        $migracion->up();

        $this->assertDatabaseHas('usuario_permiso', [
            'usuario_id' => $usuario->id,
            'permiso_id' => Permiso::firstOrCreate(['modulo' => 'pagos', 'accion' => 'crear'])->id,
            'concedido' => true,
        ]);
        $this->assertTrue($usuario->fresh()->tienePermiso('pagos', 'crear'), 'La excepción del usuario sigue dándole el permiso aunque el rol ya no lo tenga.');
    }

    public function test_un_asesor_con_el_catalogo_vigente_no_edita_clientes_ni_crea_creditos_o_pagos(): void
    {
        $asesor = $this->actingAsRolConPermisos('asesor', EmpresaConstants::PERMISOS_POR_ROL['asesor']);
        $cliente = $this->crearCliente(['asesor_id' => $asesor->id]);
        $credito = $this->crearCredito($cliente);

        $this->patchJson("/api/v1/clientes/{$cliente->id}", [])->assertStatus(403);
        $this->postJson('/api/v1/creditos', ['cliente_id' => $cliente->id])->assertStatus(403);
        $this->postJson("/api/v1/creditos/{$credito->id}/pagos", [])->assertStatus(403);
    }

    public function test_un_asesor_con_el_catalogo_vigente_sigue_pudiendo_crear_y_consultar_clientes(): void
    {
        $this->actingAsRolConPermisos('asesor', EmpresaConstants::PERMISOS_POR_ROL['asesor']);
        $cliente = $this->crearCliente();

        $this->getJson("/api/v1/clientes/{$cliente->id}")->assertOk();
        $this->getJson('/api/v1/clientes')->assertOk();
    }
}
