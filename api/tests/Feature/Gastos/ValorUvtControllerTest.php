<?php

namespace Tests\Feature\Gastos;

use App\Gastos\Models\Gasto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\Concerns\CreatesGastosFixtures;
use Tests\TestCase;

class ValorUvtControllerTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesContabilidadFixtures;
    use CreatesGastosFixtures;
    use RefreshDatabase;

    /** Un gasto causado con fecha del año dado, para probar que protege el valor UVT de ese año. */
    private function causarGastoEnAnio(int $anio): Gasto
    {
        $tipoGasto = $this->crearTipoGasto();
        $parametrizacion = $this->crearParametrizacion($tipoGasto);

        return Gasto::create([
            'tipo_gasto_id' => $tipoGasto->id,
            'fecha' => "{$anio}-03-15",
            'numero_documento' => 'FC-1',
            'concepto' => 'Gasto de prueba',
            'valor_bruto' => 100000,
            'valor_neto' => 100000,
            'parametrizacion_tipo_gasto_id' => $parametrizacion->id,
        ]);
    }

    public function test_sin_autenticar_no_puede_editar(): void
    {
        $this->actingAsAdmin();
        $valorUvt = $this->crearValorUvt(2027);
        $this->salirDeSesion();

        $this->patchJson("/api/v1/gastos/valores-uvt/{$valorUvt->id}", ['valor' => 48000])->assertStatus(401);
    }

    public function test_sin_permiso_no_puede_editar(): void
    {
        $this->actingAsAdmin();
        $valorUvt = $this->crearValorUvt(2027);
        $this->actingAsAsesor();

        $this->patchJson("/api/v1/gastos/valores-uvt/{$valorUvt->id}", ['valor' => 48000])->assertStatus(403);
    }

    public function test_editar_exige_un_valor_valido(): void
    {
        $this->actingAsAdmin();
        $valorUvt = $this->crearValorUvt(2027);

        $this->patchJson("/api/v1/gastos/valores-uvt/{$valorUvt->id}", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('valor');

        $this->patchJson("/api/v1/gastos/valores-uvt/{$valorUvt->id}", ['valor' => -5])
            ->assertStatus(422)
            ->assertJsonValidationErrors('valor');
    }

    public function test_un_valor_uvt_inexistente_responde_404_al_editar(): void
    {
        $this->actingAsAdmin();

        $this->patchJson('/api/v1/gastos/valores-uvt/999999', ['valor' => 48000])->assertStatus(404);
    }

    public function test_edita_un_valor_uvt(): void
    {
        $this->actingAsAdmin();
        $valorUvt = $this->crearValorUvt(2027, 47065);

        $respuesta = $this->patchJson("/api/v1/gastos/valores-uvt/{$valorUvt->id}", ['valor' => 49799]);

        $respuesta->assertStatus(200)->assertJsonPath('valor_uvt.anio', 2027);
        $this->assertDatabaseHas('valores_uvt', ['id' => $valorUvt->id, 'anio' => 2027, 'valor' => 4979900]);
    }

    public function test_no_se_puede_editar_un_anio_con_gastos_causados(): void
    {
        $this->actingAsAdmin();
        $valorUvt = $this->crearValorUvt(2027, 47065);
        $this->causarGastoEnAnio(2027);

        $this->patchJson("/api/v1/gastos/valores-uvt/{$valorUvt->id}", ['valor' => 49799])
            ->assertStatus(422)
            ->assertJsonValidationErrors('valor_uvt');

        $this->assertDatabaseHas('valores_uvt', ['id' => $valorUvt->id, 'valor' => 4706500]);
    }

    public function test_sin_autenticar_no_puede_eliminar(): void
    {
        $this->actingAsAdmin();
        $valorUvt = $this->crearValorUvt(2028);
        $this->salirDeSesion();

        $this->deleteJson("/api/v1/gastos/valores-uvt/{$valorUvt->id}")->assertStatus(401);
    }

    public function test_sin_permiso_no_puede_eliminar(): void
    {
        $this->actingAsAdmin();
        $valorUvt = $this->crearValorUvt(2028);
        $this->actingAsAsesor();

        $this->deleteJson("/api/v1/gastos/valores-uvt/{$valorUvt->id}")->assertStatus(403);
    }

    public function test_un_valor_uvt_inexistente_responde_404_al_eliminar(): void
    {
        $this->actingAsAdmin();

        $this->deleteJson('/api/v1/gastos/valores-uvt/999999')->assertStatus(404);
    }

    public function test_elimina_un_valor_uvt(): void
    {
        $this->actingAsAdmin();
        $valorUvt = $this->crearValorUvt(2028);

        $this->deleteJson("/api/v1/gastos/valores-uvt/{$valorUvt->id}")->assertStatus(204);

        $this->assertSoftDeleted('valores_uvt', ['id' => $valorUvt->id]);
    }

    public function test_no_se_puede_eliminar_un_anio_con_gastos_causados(): void
    {
        $this->actingAsAdmin();
        $valorUvt = $this->crearValorUvt(2028);
        $this->causarGastoEnAnio(2028);

        $this->deleteJson("/api/v1/gastos/valores-uvt/{$valorUvt->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('valor_uvt');

        $this->assertDatabaseHas('valores_uvt', ['id' => $valorUvt->id, 'borrado_en' => null]);
    }
}
