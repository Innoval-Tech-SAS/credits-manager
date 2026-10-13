<?php

namespace Tests\Feature\ObligacionesTributarias;

use App\ObligacionesTributarias\Models\AlertaObligacionTributaria;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesObligacionesTributariasFixtures;
use Tests\TestCase;

class AlertaObligacionTributariaControllerTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesObligacionesTributariasFixtures;
    use RefreshDatabase;

    /** `travelTo` no se resetea sola entre tests en esta suite (la app no se destruye entre ellos). */
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_lista_las_alertas_generadas_por_el_comando(): void
    {
        $this->actingAsAdmin();
        $tipo = $this->crearTipoObligacion(['dias_alerta_previa' => 5]);
        $this->crearObligacion($tipo, ['fecha_limite' => '2026-10-15']);
        $this->travelTo(now()->setDate(2026, 10, 12));

        $this->artisan('obligaciones_tributarias:evaluar-alertas')->assertSuccessful();

        $respuesta = $this->getJson('/api/v1/obligaciones-tributarias/alertas');

        $respuesta->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_marca_una_alerta_como_vista(): void
    {
        $this->actingAsAdmin();
        $tipo = $this->crearTipoObligacion(['dias_alerta_previa' => 5]);
        $this->crearObligacion($tipo, ['fecha_limite' => '2026-10-15']);
        $this->travelTo(now()->setDate(2026, 10, 12));
        $this->artisan('obligaciones_tributarias:evaluar-alertas');

        $alertaId = AlertaObligacionTributaria::first()->id;

        $respuesta = $this->postJson("/api/v1/obligaciones-tributarias/alertas/{$alertaId}/vista");

        $respuesta->assertStatus(200);
        $this->assertNotNull($respuesta->json('alerta.vista_en'));
    }

    public function test_sin_autenticar_no_puede_listar_alertas(): void
    {
        $this->getJson('/api/v1/obligaciones-tributarias/alertas')->assertStatus(401);
    }

    public function test_asesor_sin_permiso_no_puede_listar_alertas(): void
    {
        $this->actingAsAsesor();

        $this->getJson('/api/v1/obligaciones-tributarias/alertas')->assertStatus(403);
    }

    public function test_un_admin_no_ve_alertas_de_otra_empresa(): void
    {
        $this->actingAsAdmin();
        $tipoAjeno = $this->crearTipoObligacion(['dias_alerta_previa' => 5]);
        $this->crearObligacion($tipoAjeno, ['fecha_limite' => '2026-10-15']);
        $this->travelTo(now()->setDate(2026, 10, 12));
        $this->artisan('obligaciones_tributarias:evaluar-alertas');

        $this->actingAsAdmin();

        $respuesta = $this->getJson('/api/v1/obligaciones-tributarias/alertas');

        $respuesta->assertStatus(200)->assertJsonCount(0, 'data');
    }
}
