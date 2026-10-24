<?php

namespace Tests\Feature\Conciliacion;

use App\Conciliacion\Enums\EstadoExtracto;
use App\Contabilidad\Models\AsientoContable;
use App\Contabilidad\Models\PlanCuenta;
use App\Contabilidad\Services\ComprobanteService;
use App\Core\Dinero\Dinero;
use App\Empresas\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesConciliacionFixtures;
use Tests\TestCase;

class CierreExtractoControllerTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesConciliacionFixtures;
    use RefreshDatabase;

    private function asientoEnCuenta(PlanCuenta $cuenta, string $valor, string $fecha = '2026-10-05'): AsientoContable
    {
        $contraCuenta = $this->crearCuenta();
        $tipo = $this->crearTipoComprobante();
        $monto = Dinero::desdePesos($valor);

        $lineas = [
            ['cuenta_id' => $cuenta->id, 'debito' => $monto, 'credito' => 0],
            ['cuenta_id' => $contraCuenta->id, 'debito' => 0, 'credito' => $monto],
        ];

        $comprobante = app(ComprobanteService::class)->crearComprobante($tipo->id, $fecha, $lineas, 'Asiento de prueba');

        return AsientoContable::where('comprobante_id', $comprobante->id)->where('cuenta_id', $cuenta->id)->firstOrFail();
    }

    public function test_sin_autenticar_recibe_401(): void
    {
        $this->postJson('/api/v1/conciliacion/extractos/1/cerrar')->assertStatus(401);
        $this->postJson('/api/v1/conciliacion/extractos/1/reabrir', ['motivo' => 'Ajuste'])->assertStatus(401);
    }

    public function test_asesor_sin_permiso_recibe_403(): void
    {
        $admin = $this->actingAsAdmin();
        $cuenta = $this->crearCuentaBancaria();
        $extracto = $this->crearExtractoBancario($cuenta);

        $this->actingAsAsesor(Empresa::findOrFail($admin->empresa_id));

        $this->postJson("/api/v1/conciliacion/extractos/{$extracto->id}/cerrar")->assertStatus(403);
    }

    public function test_cerrar_exige_que_no_haya_movimientos_pendientes(): void
    {
        $this->actingAsAdmin();
        $cuenta = $this->crearCuentaBancaria();
        $extracto = $this->crearExtractoBancario($cuenta, ['saldo_actual' => 1000]);
        $this->crearMovimientoExtracto($extracto, ['valor' => 1000]);
        $this->asientoEnCuenta($cuenta->planCuenta, '1000');

        $this->postJson("/api/v1/conciliacion/extractos/{$extracto->id}/cerrar")
            ->assertStatus(422)
            ->assertJsonValidationErrors('extracto');

        $this->assertDatabaseHas('extractos_bancarios', ['id' => $extracto->id, 'estado' => 'cargado']);
    }

    public function test_cerrar_rechaza_cuando_el_saldo_libro_no_coincide_con_el_extracto(): void
    {
        $this->actingAsAdmin();
        $cuenta = $this->crearCuentaBancaria();
        $extracto = $this->crearExtractoBancario($cuenta, ['saldo_actual' => 1000]);
        $this->asientoEnCuenta($cuenta->planCuenta, '700');

        $this->postJson("/api/v1/conciliacion/extractos/{$extracto->id}/cerrar")
            ->assertStatus(422)
            ->assertJsonValidationErrors('extracto');

        $this->assertDatabaseHas('extractos_bancarios', ['id' => $extracto->id, 'estado' => 'cargado']);
    }

    public function test_cierra_un_extracto_cuadrado(): void
    {
        $this->actingAsAdmin();
        $cuenta = $this->crearCuentaBancaria();
        $extracto = $this->crearExtractoBancario($cuenta, ['fecha_hasta' => '2026-10-10', 'saldo_actual' => 1000]);
        $this->asientoEnCuenta($cuenta->planCuenta, '1000', '2026-10-05');

        $respuesta = $this->postJson("/api/v1/conciliacion/extractos/{$extracto->id}/cerrar")->assertStatus(200);

        $this->assertSame('cerrado', $respuesta->json('extracto.estado'));
        $this->assertDatabaseHas('extractos_bancarios', ['id' => $extracto->id, 'estado' => 'cerrado']);
        $this->assertNotNull($respuesta->json('extracto.cerrado_en'));
    }

    public function test_un_extracto_de_otra_empresa_responde_404_al_cerrar_y_reabrir(): void
    {
        $this->actingAsAdmin();
        $cuenta = $this->crearCuentaBancaria();
        $extracto = $this->crearExtractoBancario($cuenta, ['estado' => EstadoExtracto::Cerrado]);

        $this->actingAsAdmin();

        $this->postJson("/api/v1/conciliacion/extractos/{$extracto->id}/cerrar")->assertStatus(404);
        $this->postJson("/api/v1/conciliacion/extractos/{$extracto->id}/reabrir", ['motivo' => 'Intento ajeno'])->assertStatus(404);
    }

    public function test_reabrir_como_contable_responde_403(): void
    {
        $admin = $this->actingAsAdmin();
        $cuenta = $this->crearCuentaBancaria();
        $extracto = $this->crearExtractoBancario($cuenta, ['estado' => EstadoExtracto::Cerrado]);

        $this->actingAsRol('contable', Empresa::findOrFail($admin->empresa_id));

        $this->postJson("/api/v1/conciliacion/extractos/{$extracto->id}/reabrir", ['motivo' => 'Ajuste solicitado'])
            ->assertStatus(403);
    }

    public function test_reabrir_un_extracto_cerrado_como_admin(): void
    {
        $this->actingAsAdmin();
        $cuenta = $this->crearCuentaBancaria();
        $extracto = $this->crearExtractoBancario($cuenta, ['estado' => EstadoExtracto::Cerrado]);

        $respuesta = $this->postJson("/api/v1/conciliacion/extractos/{$extracto->id}/reabrir", ['motivo' => 'Ajuste autorizado por el contador'])
            ->assertStatus(200);

        $this->assertSame('cargado', $respuesta->json('extracto.estado'));
        $this->assertDatabaseHas('extractos_bancarios', [
            'id' => $extracto->id,
            'estado' => 'cargado',
            'motivo_reapertura' => 'Ajuste autorizado por el contador',
        ]);
    }

    public function test_reabrir_exige_motivo(): void
    {
        $this->actingAsAdmin();
        $cuenta = $this->crearCuentaBancaria();
        $extracto = $this->crearExtractoBancario($cuenta, ['estado' => EstadoExtracto::Cerrado]);

        $this->postJson("/api/v1/conciliacion/extractos/{$extracto->id}/reabrir", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('motivo');
    }

    public function test_reabrir_un_extracto_que_no_esta_cerrado_responde_422(): void
    {
        $this->actingAsAdmin();
        $cuenta = $this->crearCuentaBancaria();
        $extracto = $this->crearExtractoBancario($cuenta);

        $this->postJson("/api/v1/conciliacion/extractos/{$extracto->id}/reabrir", ['motivo' => 'Ajuste autorizado'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('extracto');
    }
}
