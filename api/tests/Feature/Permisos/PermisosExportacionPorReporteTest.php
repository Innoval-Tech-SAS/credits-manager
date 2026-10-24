<?php

namespace Tests\Feature\Permisos;

use App\Empresas\Models\Empresa;
use App\Exportacion\Constants\ExportacionConstants;
use App\Exportacion\Services\AccesoExportaciones;
use App\Usuarios\Models\Permiso;
use App\Usuarios\Models\Usuario;
use Database\Seeders\PermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesAuditoriaFixtures;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\Concerns\CreatesFacturacionFixtures;
use Tests\TestCase;

/**
 * Cada exportación se autoriza con su permiso propio (`ExportacionConstants::PERMISOS_POR_REPORTE`): el de otro reporte,
 * aunque sea del mismo módulo, no alcanza, y el `<modulo>.exportar` genérico ya no concede nada.
 */
class PermisosExportacionPorReporteTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesAuditoriaFixtures;
    use CreatesCreditFixtures;
    use CreatesFacturacionFixtures;
    use RefreshDatabase;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermisoSeeder::class);
    }

    private int $creditoId;

    private function prepararEmpresa(): void
    {
        $admin = $this->actingAsAdmin();
        $this->empresa = Empresa::findOrFail($admin->empresa_id);
        $this->creditoId = $this->crearCredito($this->crearCliente())->id;
    }

    private function actuarCon(array $permisos): Usuario
    {
        return $this->actingAsRolConPermisos('rol_'.uniqid(), $permisos, $this->empresa);
    }

    /** @return list<array{0: string, 1: string, 2: list<string>}> [uri, clave del reporte, permisos de lectura que exige la ruta] */
    private function exportaciones(): array
    {
        return [
            ['/api/v1/clientes/exportar', 'clientes.listado', []],
            ['/api/v1/creditos/exportar', 'creditos.listado', []],
            ["/api/v1/creditos/{$this->creditoId}/estado-cuenta/exportar", 'creditos.estado_cuenta', []],
            ['/api/v1/pagos/exportar', 'pagos.listado', []],
            ['/api/v1/cartera/exportar', 'cartera.listado', []],
            ['/api/v1/cartera/reporte-empleador/exportar', 'cartera.reporte_empleador', []],
            ['/api/v1/gastos/exportar', 'gastos.listado', ['gastos.listar_gastos']],
            ['/api/v1/gastos/reporte-centro-costo/exportar', 'gastos.centro_costo', ['gastos.listar_gastos_centro_costo']],
            ['/api/v1/contabilidad/libro-diario/exportar', 'contabilidad.libro_diario', ['informes_contables.listar_libro_diario']],
            ['/api/v1/contabilidad/balance-prueba/exportar', 'contabilidad.balance_prueba', ['informes_contables.listar_balance_prueba']],
            ['/api/v1/contabilidad/estados-financieros/exportar', 'contabilidad.estados_financieros', ['informes_contables.listar_estados_financieros']],
            ['/api/v1/contabilidad/auxiliares/exportar', 'contabilidad.auxiliar', ['informes_contables.listar_auxiliares']],
            ['/api/v1/auditoria/bitacora/exportar', 'auditoria.bitacora', ['auditoria.listar']],
        ];
    }

    /** @return list<string> todos los permisos de exportar por reporte */
    private function todosLosPermisosDeExportar(): array
    {
        return collect(ExportacionConstants::PERMISOS_POR_REPORTE)->flatten()->unique()->values()->all();
    }

    public function test_cada_exportacion_pasa_con_su_permiso_y_su_lectura(): void
    {
        $this->prepararEmpresa();

        foreach ($this->exportaciones() as [$uri, $reporte, $lectura]) {
            $this->actuarCon([...$lectura, ExportacionConstants::PERMISOS_POR_REPORTE[$reporte][0]]);

            $estado = $this->postJson($uri, [])->status();

            $this->assertNotSame(403, $estado, "{$uri} debe aceptar a quien tiene el permiso de {$reporte}.");
            $this->assertLessThan(500, $estado, "{$uri} no debe fallar.");
        }
    }

    public function test_el_permiso_de_cualquier_otro_reporte_no_alcanza(): void
    {
        $this->prepararEmpresa();

        foreach ($this->exportaciones() as [$uri, $reporte, $lectura]) {
            $vecinos = array_values(array_diff($this->todosLosPermisosDeExportar(), ExportacionConstants::PERMISOS_POR_REPORTE[$reporte]));
            $this->actuarCon([...$lectura, ...$vecinos]);

            $this->postJson($uri, [])->assertForbidden();
        }
    }

    public function test_el_exportar_generico_retirado_ya_no_concede_ninguna_exportacion(): void
    {
        $this->prepararEmpresa();

        // `<modulo>.exportar` ya no está en el catálogo vigente (PermisoSeeder): se simula uno retirado, como
        // quedaría tras una reorganización del catálogo, para probar que una asignación residual no concede nada.
        $generico = Permiso::withTrashed()->firstOrCreate(['modulo' => 'clientes', 'accion' => 'exportar']);

        if (! $generico->trashed()) {
            $generico->delete();
        }

        $genericos = collect([$generico->id]);

        foreach ($this->exportaciones() as [$uri, , $lectura]) {
            $usuario = $this->actuarCon($lectura);
            foreach ($genericos as $permisoId) {
                DB::table('rol_permiso')->insert(['rol_id' => $usuario->rol_id, 'permiso_id' => $permisoId, 'creado_en' => now(), 'ultima_actualizacion' => now()]);
            }

            $this->postJson($uri, [])->assertForbidden();
        }
    }

    public function test_caja_y_bancos_exporta_solo_cuentas_de_la_clase_11_con_su_propio_permiso(): void
    {
        $this->prepararEmpresa();
        $caja = $this->crearCuenta(['codigo' => '110505', 'nombre' => 'Caja general']);
        $ingresos = $this->crearCuenta(['codigo' => '413505', 'nombre' => 'Intereses', 'naturaleza' => 'credito', 'tipo' => 'ingreso']);
        $cuerpo = fn (int $cuentaId) => ['cuenta_id' => $cuentaId, 'desde' => '2026-01-01', 'hasta' => '2026-01-31', 'formato' => 'xlsx'];

        $this->actuarCon(['informes_contables.listar_caja_bancos', 'informes_contables.exportar_caja_bancos']);
        $this->postJson('/api/v1/contabilidad/libro-mayor/exportar', $cuerpo($caja->id))->assertStatus(202);
        $this->assertDatabaseHas('exportaciones', ['reporte' => 'contabilidad.caja_bancos']);
        $this->postJson('/api/v1/contabilidad/libro-mayor/exportar', $cuerpo($ingresos->id))->assertForbidden();

        $this->actuarCon(['informes_contables.listar_libro_mayor', 'informes_contables.exportar_caja_bancos']);
        $this->postJson('/api/v1/contabilidad/libro-mayor/exportar', $cuerpo($ingresos->id))->assertForbidden();
        $this->postJson('/api/v1/contabilidad/libro-mayor/exportar', $cuerpo($caja->id))->assertStatus(202);

        $this->actuarCon(['informes_contables.listar_libro_mayor', 'informes_contables.exportar_libro_mayor']);
        $this->postJson('/api/v1/contabilidad/libro-mayor/exportar', $cuerpo($ingresos->id))->assertStatus(202);
        $this->assertDatabaseHas('exportaciones', ['reporte' => 'contabilidad.libro_mayor']);
        $this->postJson('/api/v1/contabilidad/libro-mayor/exportar', $cuerpo($caja->id))->assertStatus(202);
    }

    public function test_leer_un_reporte_no_permite_exportarlo(): void
    {
        $this->prepararEmpresa();
        $this->actuarCon(['informes_contables.listar_libro_diario', 'gastos.listar_gastos', 'clientes.listar', 'auditoria.listar']);

        $this->postJson('/api/v1/contabilidad/libro-diario/exportar', [])->assertForbidden();
        $this->postJson('/api/v1/gastos/exportar', [])->assertForbidden();
        $this->postJson('/api/v1/clientes/exportar', [])->assertForbidden();
        $this->postJson('/api/v1/auditoria/bitacora/exportar', [])->assertForbidden();
    }

    public function test_el_superadmin_solo_exporta_y_descarga_reportes_de_plataforma(): void
    {
        $superadmin = $this->actingAsSuperAdmin();
        $acceso = app(AccesoExportaciones::class);

        $this->assertTrue($acceso->puede($superadmin, 'auditoria.bitacora'));
        $this->assertFalse($acceso->puede($superadmin, 'clientes.listado'));
        $this->assertFalse($acceso->puede($superadmin, 'reporte.inexistente'));
    }

    public function test_cada_permiso_del_mapa_esta_en_el_catalogo_vigente(): void
    {
        $vigentes = DB::table('permisos')->whereNull('borrado_en')->get(['modulo', 'accion'])
            ->map(fn ($permiso) => "{$permiso->modulo}.{$permiso->accion}")
            ->all();

        foreach ($this->todosLosPermisosDeExportar() as $clave) {
            $this->assertContains($clave, $vigentes, "{$clave} debe existir tras migrar.");
        }
    }
}
