<?php

namespace Tests\Feature\Contabilidad;

use App\Contabilidad\Enums\EstadoPeriodo;
use App\Contabilidad\Models\PeriodoContable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class PeriodoContableControllerTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    public function test_cierra_un_periodo(): void
    {
        $this->actingAsAdmin();
        $periodo = PeriodoContable::create(['anio' => 2026, 'mes' => 1, 'estado' => EstadoPeriodo::Abierto]);

        $respuesta = $this->postJson("/api/v1/contabilidad/periodos/{$periodo->id}/cerrar");

        $respuesta->assertStatus(200)->assertJsonPath('periodo.estado', 'cerrado');
        $this->assertDatabaseHas('periodos_contables', ['id' => $periodo->id, 'estado' => 'cerrado']);
    }

    public function test_reabre_un_periodo_con_motivo(): void
    {
        $this->actingAsAdmin();
        $periodo = PeriodoContable::create(['anio' => 2026, 'mes' => 1, 'estado' => EstadoPeriodo::Cerrado]);

        $respuesta = $this->postJson(
            "/api/v1/contabilidad/periodos/{$periodo->id}/reabrir",
            ['motivo' => 'Ajuste autorizado por contador'],
        );

        $respuesta->assertStatus(200)->assertJsonPath('periodo.estado', 'abierto');
        $this->assertDatabaseHas('periodos_contables', [
            'id' => $periodo->id,
            'estado' => 'abierto',
            'reabierto_motivo' => 'Ajuste autorizado por contador',
        ]);
    }

    public function test_reabrir_exige_motivo(): void
    {
        $this->actingAsAdmin();
        $periodo = PeriodoContable::create(['anio' => 2026, 'mes' => 1, 'estado' => EstadoPeriodo::Cerrado]);

        $respuesta = $this->postJson("/api/v1/contabilidad/periodos/{$periodo->id}/reabrir", []);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('motivo');
    }

    public function test_asesor_sin_permiso_no_puede_reabrir(): void
    {
        $this->actingAsAsesor();
        $periodo = PeriodoContable::create(['anio' => 2026, 'mes' => 1, 'estado' => EstadoPeriodo::Cerrado]);

        $respuesta = $this->postJson("/api/v1/contabilidad/periodos/{$periodo->id}/reabrir", ['motivo' => 'x']);

        $respuesta->assertStatus(403);
    }
}
