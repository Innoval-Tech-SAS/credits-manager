<?php

namespace Tests\Feature\Gastos;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\Concerns\CreatesGastosFixtures;
use Tests\TestCase;

class ParametrizacionTipoGastoControllerTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesContabilidadFixtures;
    use CreatesGastosFixtures;
    use RefreshDatabase;

    public function test_crea_una_parametrizacion_vigente(): void
    {
        $this->actingAsAdmin();
        $tipoGasto = $this->crearTipoGasto();

        $respuesta = $this->postJson('/api/v1/gastos/parametrizaciones', [
            'tipo_gasto_id' => $tipoGasto->id,
            'fecha_desde' => '2026-01-01',
            'tipo_comprobante_id' => $this->crearTipoComprobante()->id,
            'cuenta_gasto_id' => $this->crearCuenta()->id,
            'cuenta_cxp_id' => $this->crearCuenta()->id,
        ]);

        $respuesta->assertStatus(201);
        $this->assertDatabaseHas('parametrizaciones_tipo_gasto', ['tipo_gasto_id' => $tipoGasto->id, 'fecha_hasta' => null]);
    }

    public function test_cierra_la_vigencia_abierta_al_crear_una_nueva_version_sin_sobrescribirla(): void
    {
        $this->actingAsAdmin();
        $tipoGasto = $this->crearTipoGasto();
        $vigenteAnterior = $this->crearParametrizacion($tipoGasto, ['fecha_desde' => '2025-01-01']);

        $this->postJson('/api/v1/gastos/parametrizaciones', [
            'tipo_gasto_id' => $tipoGasto->id,
            'fecha_desde' => '2026-01-01',
            'tipo_comprobante_id' => $this->crearTipoComprobante()->id,
            'cuenta_gasto_id' => $this->crearCuenta()->id,
            'cuenta_cxp_id' => $this->crearCuenta()->id,
        ])->assertStatus(201);

        $this->assertDatabaseHas('parametrizaciones_tipo_gasto', [
            'id' => $vigenteAnterior->id,
            'fecha_hasta' => '2025-12-31',
        ]);
        $this->assertDatabaseHas('parametrizaciones_tipo_gasto', [
            'tipo_gasto_id' => $tipoGasto->id,
            'fecha_desde' => '2026-01-01',
            'fecha_hasta' => null,
        ]);
    }

    public function test_rechaza_fecha_desde_dentro_de_un_rango_ya_cerrado(): void
    {
        $this->actingAsAdmin();
        $tipoGasto = $this->crearTipoGasto();
        $this->crearParametrizacion($tipoGasto, ['fecha_desde' => '2025-01-01', 'fecha_hasta' => '2025-12-31']);

        $respuesta = $this->postJson('/api/v1/gastos/parametrizaciones', [
            'tipo_gasto_id' => $tipoGasto->id,
            'fecha_desde' => '2025-06-15',
            'tipo_comprobante_id' => $this->crearTipoComprobante()->id,
            'cuenta_gasto_id' => $this->crearCuenta()->id,
            'cuenta_cxp_id' => $this->crearCuenta()->id,
        ]);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('fecha_desde');
    }

    public function test_causar_gasto_sin_parametrizacion_vigente_es_rechazado(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();
        $tipoGasto = $this->crearTipoGasto(['requiere_tercero' => false]);

        $respuesta = $this->postJson('/api/v1/gastos', [
            'tipo_gasto_id' => $tipoGasto->id, 'centro_costo_id' => $this->crearCentroCosto()->id,
            'fecha' => '2026-03-01',
            'numero_documento' => 'FAC-1',
            'concepto' => 'Gasto sin parametrizar',
            'valor_bruto' => 100000,
            'soporte' => UploadedFile::fake()->create('soporte.pdf', 200, 'application/pdf'),
        ]);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('tipo_gasto_id');
    }

    public function test_requiere_tarifa_de_retefuente_si_aplica_retefuente(): void
    {
        $this->actingAsAdmin();
        $tipoGasto = $this->crearTipoGasto();

        $respuesta = $this->postJson('/api/v1/gastos/parametrizaciones', [
            'tipo_gasto_id' => $tipoGasto->id,
            'fecha_desde' => '2026-01-01',
            'tipo_comprobante_id' => $this->crearTipoComprobante()->id,
            'cuenta_gasto_id' => $this->crearCuenta()->id,
            'cuenta_cxp_id' => $this->crearCuenta()->id,
            'aplica_retefuente' => true,
        ]);

        $respuesta->assertStatus(422)
            ->assertJsonValidationErrors(['tarifa_retefuente_persona_natural', 'tarifa_retefuente_persona_juridica', 'cuenta_retefuente_id']);
    }
}
