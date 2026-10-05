<?php

namespace Tests\Feature\Empresas;

use App\Usuarios\Models\Rol;
use Database\Seeders\PermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class PermisosExportarReportesTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    private const PERMISOS = ['cartera', 'contabilidad', 'creditos', 'informes_contables'];

    private const MODULOS_HISTORICOS = ['cartera', 'contabilidad', 'creditos'];

    /** @return list<string> claves "modulo.accion" de exportar de estos reportes que tiene el rol */
    private function exportarDelRol(int $empresaId, string $rol): array
    {
        return DB::table('rol_permiso')
            ->join('roles', 'roles.id', '=', 'rol_permiso.rol_id')
            ->join('permisos', 'permisos.id', '=', 'rol_permiso.permiso_id')
            ->where('roles.empresa_id', $empresaId)
            ->where('roles.nombre', $rol)
            ->where('permisos.accion', 'exportar')
            ->whereIn('permisos.modulo', self::PERMISOS)
            ->orderBy('permisos.modulo')
            ->get(['permisos.modulo', 'permisos.accion'])
            ->map(fn ($permiso) => "{$permiso->modulo}.{$permiso->accion}")->all();
    }

    public function test_el_catalogo_incluye_los_tres_permisos_de_exportar(): void
    {
        $this->seed(PermisoSeeder::class);

        foreach (['cartera', 'creditos', 'informes_contables'] as $modulo) {
            $this->assertDatabaseHas('permisos', ['modulo' => $modulo, 'accion' => 'exportar']);
        }
    }

    public function test_una_empresa_nueva_asigna_exportar_solo_a_admin_contable_y_auditoria(): void
    {
        $this->seed(PermisoSeeder::class);
        $this->actingAsSuperAdmin();

        $empresaId = $this->postJson('/api/v1/empresas', [
            'nombre_empresa' => 'Empresa exportadora SAS', 'nit' => '900777666-1', 'direccion' => 'Calle 1', 'municipio_id' => $this->municipioDePrueba()->id, 'telefono' => '3001112222',
            'correo' => 'exportadora@example.com', 'admin_nombre' => 'Admin', 'admin_usuario' => 'admin_exportador', 'admin_password' => 'Clave123!',
        ])->assertCreated()->json('empresa.id');

        // El admin recibe todas las filas de `permisos`, incluido el `contabilidad.exportar` que dejó la migración de 2026-09-21.
        $this->assertSame(['cartera.exportar', 'contabilidad.exportar', 'creditos.exportar', 'informes_contables.exportar'], $this->exportarDelRol($empresaId, 'admin'));
        $this->assertSame(['cartera.exportar', 'informes_contables.exportar'], $this->exportarDelRol($empresaId, 'contable'));
        $this->assertSame(['cartera.exportar', 'informes_contables.exportar'], $this->exportarDelRol($empresaId, 'auditoria'));

        foreach (['asesor', 'cartera', 'consulta'] as $rol) {
            $this->assertSame([], $this->exportarDelRol($empresaId, $rol), "El rol {$rol} no debe exportar.");
        }
    }

    public function test_la_migracion_de_datos_lo_agrega_a_las_empresas_existentes_y_se_revierte(): void
    {
        $empresa = $this->crearEmpresaDePrueba();

        foreach (['admin', 'contable', 'auditoria', 'asesor'] as $nombre) {
            Rol::create(['empresa_id' => $empresa->id, 'nombre' => $nombre]);
        }

        $migracion = require database_path('migrations/2026_09_21_030000_agregar_permisos_exportar_reportes.php');

        $migracion->up();
        $migracion->up();

        $this->assertSame(['cartera.exportar', 'contabilidad.exportar', 'creditos.exportar'], $this->exportarDelRol($empresa->id, 'admin'));
        $this->assertSame(['cartera.exportar', 'contabilidad.exportar'], $this->exportarDelRol($empresa->id, 'contable'));
        $this->assertSame(['cartera.exportar', 'contabilidad.exportar'], $this->exportarDelRol($empresa->id, 'auditoria'));
        $this->assertSame([], $this->exportarDelRol($empresa->id, 'asesor'));
        $this->assertSame(3, DB::table('permisos')->where('accion', 'exportar')->whereIn('modulo', self::MODULOS_HISTORICOS)->count(), 'Aplicarla dos veces no duplica.');

        $migracion->down();

        $this->assertSame(0, DB::table('permisos')->where('accion', 'exportar')->whereIn('modulo', self::MODULOS_HISTORICOS)->count());
        $this->assertSame(0, DB::table('rol_permiso')->count());
    }
}
