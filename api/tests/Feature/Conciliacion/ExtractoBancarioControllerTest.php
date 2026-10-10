<?php

namespace Tests\Feature\Conciliacion;

use App\Conciliacion\Jobs\ValidarExtractoBancarioJob;
use App\Empresas\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CargaExtractosBancarios;
use Tests\Concerns\CreatesConciliacionFixtures;
use Tests\TestCase;

class ExtractoBancarioControllerTest extends TestCase
{
    use ActsAsAdmin;
    use CargaExtractosBancarios;
    use CreatesConciliacionFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararConciliacion();
    }

    private function resumenCuadrado(): array
    {
        return ['saldo_anterior' => 1000, 'total_abonos' => 500, 'total_cargos' => 200, 'saldo_actual' => 1300];
    }

    private function movimientosCuadrados(): array
    {
        return [
            ['fecha' => '1/07', 'descripcion' => 'CONSIGNACION', 'valor' => 500, 'saldo' => 1500],
            ['fecha' => '2/07', 'descripcion' => 'RETIRO CAJERO', 'valor' => -200, 'saldo' => 1300],
        ];
    }

    public function test_sin_autenticar_no_puede_cargar(): void
    {
        $this->actingAsAdmin();
        $cuenta = $this->crearCuentaBancaria(['numero_cuenta' => '111']);
        $archivo = $this->archivoExtracto('111', $this->resumenCuadrado(), $this->movimientosCuadrados());
        $this->salirDeSesion();

        $this->postJson("/api/v1/conciliacion/cuentas-bancarias/{$cuenta->id}/extractos", ['archivo' => $archivo, 'mes_primera_fila' => '2026-07'])
            ->assertStatus(401);
    }

    public function test_asesor_sin_permiso_no_puede_cargar(): void
    {
        $admin = $this->actingAsAdmin();
        $cuenta = $this->crearCuentaBancaria(['numero_cuenta' => '111']);
        $this->actingAsAsesor(Empresa::findOrFail($admin->empresa_id));

        $this->cargarExtracto($cuenta->id, $this->archivoExtracto('111', $this->resumenCuadrado(), $this->movimientosCuadrados()))
            ->assertStatus(403);
    }

    public function test_valida_los_campos_requeridos(): void
    {
        $this->actingAsAdmin();
        $cuenta = $this->crearCuentaBancaria();

        $this->postJson("/api/v1/conciliacion/cuentas-bancarias/{$cuenta->id}/extractos", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['archivo', 'mes_primera_fila']);
    }

    public function test_rechaza_si_la_cuenta_del_nombre_del_archivo_no_coincide(): void
    {
        $this->actingAsAdmin();
        $cuenta = $this->crearCuentaBancaria(['numero_cuenta' => '111']);
        $archivo = $this->archivoExtracto('999', $this->resumenCuadrado(), $this->movimientosCuadrados());

        $this->cargarExtracto($cuenta->id, $archivo)->assertStatus(422)->assertJsonValidationErrors('archivo');
    }

    public function test_rechaza_cuando_los_saldos_no_cuadran(): void
    {
        $this->actingAsAdmin();
        $cuenta = $this->crearCuentaBancaria(['numero_cuenta' => '111']);
        $resumen = array_merge($this->resumenCuadrado(), ['saldo_actual' => 999999]);
        $archivo = $this->archivoExtracto('111', $resumen, $this->movimientosCuadrados());

        $this->cargarExtracto($cuenta->id, $archivo)->assertStatus(422)->assertJsonValidationErrors('archivo');
    }

    public function test_carga_el_extracto_y_el_job_escribe_los_movimientos(): void
    {
        $this->actingAsAdmin();
        $cuenta = $this->crearCuentaBancaria(['numero_cuenta' => '111']);
        $archivo = $this->archivoExtracto('111', $this->resumenCuadrado(), $this->movimientosCuadrados());

        $respuesta = $this->cargarExtracto($cuenta->id, $archivo)->assertStatus(202);
        $id = $respuesta->json('extracto.id');

        Queue::assertPushed(ValidarExtractoBancarioJob::class);
        $this->assertSame('cargando', $this->extracto($id)->estado->value);
        $this->assertSame('2026-07-01', $this->extracto($id)->fecha_desde->format('Y-m-d'));
        $this->assertSame('2026-07-02', $this->extracto($id)->fecha_hasta->format('Y-m-d'));
        $this->assertSame('1300.00', $this->extracto($id)->saldo_actual->aDecimalString());

        $this->procesarExtracto($id, $this->extracto($id)->empresa_id);

        $this->assertSame('cargado', $this->extracto($id)->fresh()->estado->value);
        $this->assertDatabaseCount('movimientos_extracto', 2);
        $this->assertDatabaseHas('movimientos_extracto', ['extracto_bancario_id' => $id, 'descripcion' => 'CONSIGNACION', 'valor' => 50000]);
        $this->assertDatabaseHas('notificaciones', ['tipo' => 'extracto_bancario_cargado']);
    }

    public function test_rechaza_un_extracto_que_se_solapa_con_uno_ya_vigente(): void
    {
        $this->actingAsAdmin();
        $cuenta = $this->crearCuentaBancaria(['numero_cuenta' => '111']);

        $this->cargarExtracto($cuenta->id, $this->archivoExtracto('111', $this->resumenCuadrado(), $this->movimientosCuadrados()))
            ->assertStatus(202);

        $otroArchivo = $this->archivoExtracto('111', $this->resumenCuadrado(), $this->movimientosCuadrados());

        $this->cargarExtracto($cuenta->id, $otroArchivo)->assertStatus(422)->assertJsonValidationErrors('archivo');
    }

    public function test_un_admin_no_ve_extractos_de_una_cuenta_bancaria_de_otra_empresa(): void
    {
        $this->actingAsAdmin();
        $cuentaAjena = $this->crearCuentaBancaria();

        $this->actingAsAdmin();

        $this->getJson("/api/v1/conciliacion/cuentas-bancarias/{$cuentaAjena->id}/extractos")->assertStatus(404);
    }
}
