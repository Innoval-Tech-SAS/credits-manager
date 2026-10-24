<?php

namespace Tests\Feature\Permisos;

use App\Contabilidad\Enums\EstadoPeriodo;
use App\Contabilidad\Models\CentroCosto;
use App\Contabilidad\Models\Comprobante;
use App\Contabilidad\Models\PeriodoContable;
use App\Contabilidad\Models\PlanCuenta;
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

/** Un usuario de otra empresa, aunque tenga todo el catálogo, no ve ni toca los recursos de cada módulo nuevo. */
class AislamientoModulosContablesTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesAuditoriaFixtures;
    use CreatesContabilidadFixtures;
    use CreatesGastosFixtures;
    use RefreshDatabase;

    private PlanCuenta $cuenta;

    private CentroCosto $centro;

    private Tercero $tercero;

    private Proveedor $proveedor;

    private ActividadEconomica $actividad;

    private PeriodoContable $periodo;

    private Comprobante $comprobante;

    private function prepararEmpresaAjena(): void
    {
        $this->seed(PermisoSeeder::class);

        $this->actingAsAdmin();

        // Códigos explícitos: el contador por defecto de crearCuenta() puede caer en un prefijo real de
        // cartera (1305/2205/2335) y estas cuentas no tienen nada que ver con eso.
        $this->cuenta = $this->crearCuenta(['codigo' => '910001']);
        $this->centro = CentroCosto::findOrFail($this->postJson('/api/v1/contabilidad/centros-costo', ['codigo' => 'ADM', 'nombre' => 'Administración'])
            ->assertCreated()->json('centro.id'));
        $this->tercero = $this->crearTercero(['tipo_persona' => TipoPersona::Natural, 'nombre' => 'Persona ajena']);
        $this->proveedor = Proveedor::create(['tercero_id' => $this->crearTercero(['nombre' => 'Proveedor ajeno SAS'])->id, 'activo' => true]);
        $this->actividad = $this->crearActividadEconomica();
        $this->periodo = PeriodoContable::create(['anio' => 2026, 'mes' => 3, 'estado' => EstadoPeriodo::Abierto]);
        $this->comprobante = Comprobante::findOrFail($this->postJson('/api/v1/contabilidad/comprobantes', [
            'tipo_comprobante_id' => $this->crearTipoComprobante()->id,
            'fecha' => '2026-03-10',
            'concepto' => 'Comprobante ajeno',
            'lineas' => [
                ['cuenta_id' => $this->cuenta->id, 'debito' => 50, 'credito' => 0],
                ['cuenta_id' => $this->crearCuenta(['codigo' => '910002'])->id, 'debito' => 0, 'credito' => 50],
            ],
        ])->assertCreated()->json('comprobante.id'));
    }

    public function test_un_usuario_de_otra_empresa_recibe_404_en_cada_recurso_de_los_modulos_nuevos(): void
    {
        $this->prepararEmpresaAjena();

        $this->actingAsRolConPermisos('otra_empresa_completa', $this->catalogoCompleto(), $this->crearEmpresaDePrueba());

        $this->patchJson("/api/v1/contabilidad/plan-cuentas/{$this->cuenta->id}", ['nombre' => 'Intento'])->assertNotFound();
        $this->patchJson("/api/v1/contabilidad/centros-costo/{$this->centro->id}", ['nombre' => 'Intento'])->assertNotFound();
        $this->postJson("/api/v1/contabilidad/periodos/{$this->periodo->id}/cerrar")->assertNotFound();
        $this->getJson("/api/v1/terceros/{$this->tercero->id}")->assertNotFound();
        $this->getJson("/api/v1/proveedores/{$this->proveedor->id}")->assertNotFound();
        $this->patchJson("/api/v1/gastos/actividades-economicas/{$this->actividad->id}", ['nombre' => 'Intento'])->assertNotFound();
        $this->getJson("/api/v1/contabilidad/comprobantes/{$this->comprobante->id}")->assertNotFound();
        $this->getJson("/api/v1/contabilidad/comprobantes/{$this->comprobante->id}/pdf")->assertNotFound();
        $this->postJson("/api/v1/contabilidad/comprobantes/{$this->comprobante->id}/reversar", ['motivo' => 'Intento'])->assertNotFound();
    }

    public function test_los_listados_de_otra_empresa_no_muestran_los_recursos_ajenos(): void
    {
        $this->prepararEmpresaAjena();

        $this->actingAsRolConPermisos('otra_empresa_completa', $this->catalogoCompleto(), $this->crearEmpresaDePrueba());

        $this->getJson('/api/v1/terceros')->assertOk()->assertJsonMissing(['numero_documento' => $this->tercero->numero_documento]);
        $this->getJson('/api/v1/contabilidad/plan-cuentas')->assertOk()->assertJsonMissing(['codigo' => $this->cuenta->codigo]);
        $this->getJson('/api/v1/contabilidad/periodos')->assertOk()->assertJsonMissing(['mes' => 3]);
        $this->getJson('/api/v1/contabilidad/comprobantes')->assertOk()->assertJsonMissing(['concepto' => 'Comprobante ajeno']);
    }

    public function test_el_dueno_si_ve_sus_recursos_mientras_la_otra_empresa_no(): void
    {
        $this->prepararEmpresaAjena();

        $this->getJson("/api/v1/terceros/{$this->tercero->id}")->assertOk();
        $this->getJson("/api/v1/contabilidad/comprobantes/{$this->comprobante->id}")->assertOk();
        $this->patchJson("/api/v1/gastos/actividades-economicas/{$this->actividad->id}", ['nombre' => 'Propia'])->assertOk();
    }

    /** @return list<string> */
    private function catalogoCompleto(): array
    {
        return collect(PermisoSeeder::CATALOGO)
            ->flatMap(fn (array $acciones, string $modulo) => collect($acciones)->map(fn (string $accion) => "{$modulo}.{$accion}"))
            ->values()
            ->all();
    }
}
