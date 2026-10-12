<?php

namespace Tests\Feature\Permisos;

use App\Causacion\Models\ValorUvt;
use App\Contabilidad\Enums\EstadoPeriodo;
use App\Contabilidad\Enums\EstadoSolicitudReapertura;
use App\Contabilidad\Models\Comprobante;
use App\Contabilidad\Models\PeriodoContable;
use App\Contabilidad\Models\PlanCuenta;
use App\Contabilidad\Models\SolicitudReaperturaPeriodo;
use App\Empresas\Models\Empresa;
use App\Gastos\Models\ActividadEconomica;
use App\Proveedores\Models\Proveedor;
use App\Terceros\Enums\TipoPersona;
use App\Terceros\Models\Tercero;
use Database\Seeders\PermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesAuditoriaFixtures;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\Concerns\CreatesGastosFixtures;
use Tests\TestCase;

/**
 * Cada endpoint de Terceros, Proveedores, Configuración contable, Contabilidad (operación), Informes
 * contables y Tributario exige su permiso propio: sin sesión responde 401, con el catálogo completo
 * menos ese permiso responde 403, y con el catálogo completo no responde 403.
 */
class SeparacionModulosContablesTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesAuditoriaFixtures;
    use CreatesContabilidadFixtures;
    use CreatesGastosFixtures;
    use RefreshDatabase;

    private Empresa $empresa;

    private int $adminId;

    private Tercero $terceroNatural;

    private Tercero $terceroJuridico;

    private Proveedor $proveedor;

    private PlanCuenta $cuenta;

    private int $centroId;

    private PeriodoContable $periodoAbierto;

    private PeriodoContable $periodoCerrado;

    private SolicitudReaperturaPeriodo $solicitud;

    private Comprobante $comprobante;

    private ActividadEconomica $actividad;

    private ValorUvt $valorUvt;

    /** @var list<string> todo el catálogo vigente, "modulo.accion" */
    private array $catalogo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermisoSeeder::class);

        $this->catalogo = collect(PermisoSeeder::CATALOGO)
            ->flatMap(fn (array $acciones, string $modulo) => collect($acciones)->map(fn (string $accion) => "{$modulo}.{$accion}"))
            ->values()
            ->all();
    }

    private function prepararEmpresa(): void
    {
        $admin = $this->actingAsAdmin();

        $this->empresa = Empresa::findOrFail($admin->empresa_id);
        $this->adminId = $admin->id;
    }

    private function prepararDatos(): void
    {
        $this->prepararEmpresa();

        $this->terceroNatural = $this->crearTercero(['tipo_persona' => TipoPersona::Natural, 'nombre' => 'Persona de prueba']);
        $this->terceroJuridico = $this->crearTercero(['nombre' => 'Empresa de prueba SAS']);
        $this->proveedor = Proveedor::create(['tercero_id' => $this->terceroJuridico->id, 'activo' => true]);
        $this->actividad = $this->crearActividadEconomica();
        $this->valorUvt = $this->crearValorUvt(2031, 47065);

        $this->cuenta = $this->crearCuenta();
        $this->centroId = $this->postJson('/api/v1/contabilidad/centros-costo', ['codigo' => 'ADM', 'nombre' => 'Administración'])
            ->assertCreated()->json('centro.id');

        $this->periodoAbierto = PeriodoContable::create(['anio' => 2026, 'mes' => 1, 'estado' => EstadoPeriodo::Abierto]);
        $this->periodoCerrado = PeriodoContable::create(['anio' => 2026, 'mes' => 2, 'estado' => EstadoPeriodo::Cerrado]);
        $this->solicitud = SolicitudReaperturaPeriodo::create([
            'periodo_id' => $this->periodoCerrado->id,
            'solicitado_por' => $this->adminId,
            'motivo' => 'Ajuste de prueba',
            'estado' => EstadoSolicitudReapertura::Pendiente,
        ]);

        $this->comprobante = Comprobante::findOrFail($this->postJson('/api/v1/contabilidad/comprobantes', [
            'tipo_comprobante_id' => $this->crearTipoComprobante()->id,
            'fecha' => '2026-01-15',
            'concepto' => 'Comprobante de prueba',
            'lineas' => [
                ['cuenta_id' => $this->cuenta->id, 'debito' => 100, 'credito' => 0],
                ['cuenta_id' => $this->crearCuenta()->id, 'debito' => 0, 'credito' => 100],
            ],
        ])->assertCreated()->json('comprobante.id'));
    }

    /**
     * Cada entrada es [método, uri, permiso requerido, cuerpo].
     *
     * @return list<array{0: string, 1: string, 2: string, 3: array<string, mixed>}>
     */
    private function endpoints(): array
    {
        return [
            ['GET', '/api/v1/terceros', 'terceros.listar', []],
            ['POST', '/api/v1/terceros/personas-naturales', 'terceros.crear', []],
            ['POST', '/api/v1/terceros/personas-juridicas', 'terceros.crear', []],
            ['GET', "/api/v1/terceros/{$this->terceroNatural->id}", 'terceros.listar', []],
            ['PATCH', "/api/v1/terceros/personas-naturales/{$this->terceroNatural->id}", 'terceros.editar', []],
            ['PATCH', "/api/v1/terceros/personas-juridicas/{$this->terceroJuridico->id}", 'terceros.editar', []],
            ['GET', "/api/v1/terceros/{$this->terceroJuridico->id}/documentos-pendientes?naturaleza=por_pagar", 'contabilidad.listar', []],

            ['GET', '/api/v1/proveedores', 'terceros.listar', []],
            ['POST', '/api/v1/proveedores/personas-naturales', 'terceros.crear', []],
            ['POST', '/api/v1/proveedores/personas-juridicas', 'terceros.crear', []],
            ['GET', "/api/v1/proveedores/{$this->proveedor->id}", 'terceros.listar', []],
            ['PATCH', "/api/v1/proveedores/personas-juridicas/{$this->proveedor->id}", 'terceros.editar', []],

            ['GET', '/api/v1/contabilidad/plan-cuentas', 'configuracion_contable.listar', []],
            ['POST', '/api/v1/contabilidad/plan-cuentas', 'configuracion_contable.crear', []],
            ['PATCH', "/api/v1/contabilidad/plan-cuentas/{$this->cuenta->id}", 'configuracion_contable.editar', []],
            ['GET', '/api/v1/contabilidad/periodos', 'configuracion_contable.listar', []],
            ['POST', "/api/v1/contabilidad/periodos/{$this->periodoAbierto->id}/cerrar", 'configuracion_contable.cerrar_periodo', []],
            ['POST', "/api/v1/contabilidad/periodos/{$this->periodoCerrado->id}/reabrir", 'configuracion_contable.reabrir_periodo', []],
            ['GET', '/api/v1/contabilidad/solicitudes-reapertura', 'configuracion_contable.listar', []],
            ['POST', "/api/v1/contabilidad/periodos/{$this->periodoCerrado->id}/solicitar-reapertura", 'configuracion_contable.cerrar_periodo', []],
            ['POST', "/api/v1/contabilidad/solicitudes-reapertura/{$this->solicitud->id}/autorizar", 'configuracion_contable.reabrir_periodo', []],
            ['POST', "/api/v1/contabilidad/solicitudes-reapertura/{$this->solicitud->id}/rechazar", 'configuracion_contable.reabrir_periodo', []],
            ['GET', '/api/v1/contabilidad/tipos-comprobante', 'configuracion_contable.listar', []],
            ['POST', '/api/v1/contabilidad/tipos-comprobante', 'configuracion_contable.crear', []],
            ['GET', '/api/v1/contabilidad/centros-costo', 'configuracion_contable.listar', []],
            ['POST', '/api/v1/contabilidad/centros-costo', 'configuracion_contable.crear', []],
            ['PATCH', "/api/v1/contabilidad/centros-costo/{$this->centroId}", 'configuracion_contable.editar', []],
            ['GET', '/api/v1/contabilidad/reglas', 'configuracion_contable.configurar', []],
            ['POST', '/api/v1/contabilidad/reglas', 'configuracion_contable.configurar', []],
            ['GET', '/api/v1/importaciones', 'importaciones.listar', []],

            ['GET', '/api/v1/contabilidad/comprobantes', 'contabilidad.listar', []],
            ['POST', '/api/v1/contabilidad/comprobantes', 'contabilidad.crear_manual', []],
            ['GET', "/api/v1/contabilidad/comprobantes/{$this->comprobante->id}", 'contabilidad.listar', []],
            ['GET', "/api/v1/contabilidad/comprobantes/{$this->comprobante->id}/pdf", 'contabilidad.imprimir_comprobante', []],
            ['POST', "/api/v1/contabilidad/comprobantes/{$this->comprobante->id}/reversar", 'contabilidad.anular', []],

            ['GET', '/api/v1/contabilidad/libro-diario', 'informes_contables.listar', []],
            ['GET', '/api/v1/contabilidad/libro-mayor', 'informes_contables.listar', []],
            ['GET', '/api/v1/contabilidad/balance-prueba', 'informes_contables.listar', []],
            ['GET', '/api/v1/contabilidad/estados-financieros', 'informes_contables.listar', []],
            ['POST', '/api/v1/contabilidad/libro-diario/exportar', 'informes_contables.exportar', []],
            ['POST', '/api/v1/contabilidad/libro-mayor/exportar', 'informes_contables.exportar', []],
            ['POST', '/api/v1/contabilidad/balance-prueba/exportar', 'informes_contables.exportar', []],
            ['POST', '/api/v1/contabilidad/estados-financieros/exportar', 'informes_contables.exportar', []],

            ['GET', '/api/v1/gastos/actividades-economicas', 'tributario.listar', []],
            ['POST', '/api/v1/gastos/actividades-economicas', 'tributario.parametrizar', []],
            ['PATCH', "/api/v1/gastos/actividades-economicas/{$this->actividad->id}", 'tributario.parametrizar', []],
            ['GET', '/api/v1/gastos/valores-uvt', 'tributario.listar', []],
            ['POST', '/api/v1/gastos/valores-uvt', 'tributario.parametrizar', []],
            ['PATCH', "/api/v1/gastos/valores-uvt/{$this->valorUvt->id}", 'tributario.parametrizar', []],
            ['DELETE', "/api/v1/gastos/valores-uvt/{$this->valorUvt->id}", 'tributario.parametrizar', []],
            ['GET', '/api/v1/gastos/tarifas-reteica', 'tributario.listar', []],
            ['POST', '/api/v1/gastos/tarifas-reteica', 'tributario.parametrizar', []],
        ];
    }

    private function usuarioCon(array $permisos): void
    {
        $this->actingAsRolConPermisos('rol_'.uniqid(), $permisos, $this->empresa);
    }

    public function test_sin_sesion_cada_endpoint_responde_401(): void
    {
        $this->prepararDatos();

        foreach ($this->endpoints() as [$metodo, $uri, , $cuerpo]) {
            $this->salirDeSesion();

            $this->json($metodo, $uri, $cuerpo)->assertUnauthorized();
        }
    }

    public function test_sin_su_permiso_cada_endpoint_responde_403_y_con_el_catalogo_completo_no(): void
    {
        $this->prepararDatos();

        foreach ($this->endpoints() as [$metodo, $uri, $permiso, $cuerpo]) {
            $this->usuarioCon(array_values(array_diff($this->catalogo, [$permiso])));
            $this->json($metodo, $uri, $cuerpo)->assertForbidden();

            $this->usuarioCon($this->catalogo);
            $estado = $this->json($metodo, $uri, $cuerpo)->getStatusCode();

            $this->assertNotSame(403, $estado, "{$metodo} {$uri} debería aceptar a quien tiene {$permiso}.");
            $this->assertLessThan(500, $estado, "{$metodo} {$uri} no debe fallar con el catálogo completo.");
        }
    }

    public function test_los_permisos_de_un_modulo_no_dan_acceso_a_los_demas_modulos(): void
    {
        $this->prepararDatos();

        foreach (['terceros', 'configuracion_contable', 'contabilidad', 'informes_contables', 'tributario'] as $modulo) {
            $propios = array_values(array_filter($this->catalogo, fn (string $clave) => str_starts_with($clave, "{$modulo}.")));
            $this->usuarioCon($propios);

            foreach ($this->endpoints() as [$metodo, $uri, $permiso, $cuerpo]) {
                $estado = $this->json($metodo, $uri, $cuerpo)->getStatusCode();

                if (str_starts_with($permiso, "{$modulo}.")) {
                    $this->assertNotSame(403, $estado, "Con solo {$modulo} debe entrar a {$metodo} {$uri}.");
                } else {
                    $this->assertSame(403, $estado, "Con solo {$modulo} no debe entrar a {$metodo} {$uri}.");
                }
            }
        }
    }

    public function test_un_asesor_sigue_listando_terceros_de_tipo_cliente_pero_no_los_demas(): void
    {
        $this->prepararEmpresa();
        $this->usuarioCon(['clientes.listar']);

        $this->getJson('/api/v1/terceros?tipo=cliente')->assertOk();
        $this->getJson('/api/v1/terceros?tipo=proveedor')->assertForbidden();
        $this->getJson('/api/v1/terceros')->assertForbidden();
    }

    public function test_crear_un_tercero_natural_con_terceros_crear_lo_persiste(): void
    {
        $this->prepararEmpresa();
        $this->usuarioCon(['terceros.crear']);

        $this->postJson('/api/v1/terceros/personas-naturales', [
            'tipo_documento' => 'cc',
            'numero_documento' => '1000000001',
            'nombres' => 'Luisa',
            'apellidos' => 'Fernández',
        ])->assertCreated();

        $this->assertDatabaseHas('terceros', ['numero_documento' => '1000000001', 'tipo_persona' => 'natural', 'nombres' => 'Luisa']);
    }

    public function test_crear_un_tercero_sin_datos_responde_422_con_su_permiso(): void
    {
        $this->prepararEmpresa();
        $this->usuarioCon(['terceros.crear']);

        $this->postJson('/api/v1/terceros/personas-naturales', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['numero_documento']);
    }

    public function test_crear_una_cuenta_con_configuracion_crear_la_persiste(): void
    {
        $this->prepararEmpresa();
        $this->usuarioCon(['configuracion_contable.crear']);

        $this->postJson('/api/v1/contabilidad/plan-cuentas', [
            'codigo' => '1105',
            'nombre' => 'Caja general',
            'naturaleza' => 'debito',
            'tipo' => 'activo',
            'permite_movimiento' => true,
        ])->assertCreated();

        $this->assertDatabaseHas('plan_cuentas', ['codigo' => '1105', 'nombre' => 'Caja general']);
    }

    public function test_actualizar_una_actividad_con_tributario_parametrizar_la_persiste(): void
    {
        $this->prepararDatos();
        $this->usuarioCon(['tributario.parametrizar']);

        $this->patchJson("/api/v1/gastos/actividades-economicas/{$this->actividad->id}", ['nombre' => 'Actividad renombrada'])
            ->assertOk();

        $this->assertDatabaseHas('actividades_economicas', ['id' => $this->actividad->id, 'nombre' => 'Actividad renombrada']);
    }

    public function test_exportar_un_informe_con_informes_exportar_crea_la_exportacion(): void
    {
        $this->prepararEmpresa();
        $this->usuarioCon(['informes_contables.exportar']);

        $this->postJson('/api/v1/contabilidad/libro-diario/exportar', [
            'desde' => '2026-01-01',
            'hasta' => '2026-01-31',
            'formato' => 'xlsx',
        ])->assertStatus(202);

        $this->assertDatabaseHas('exportaciones', ['reporte' => 'contabilidad.libro_diario']);
    }

    public function test_ver_el_libro_diario_solo_necesita_informes_listar(): void
    {
        $this->prepararEmpresa();
        $this->usuarioCon(['informes_contables.listar']);

        $this->getJson('/api/v1/contabilidad/libro-diario?desde=2026-01-01&hasta=2026-01-31')->assertOk();
        $this->getJson('/api/v1/contabilidad/comprobantes')->assertForbidden();
    }
}
