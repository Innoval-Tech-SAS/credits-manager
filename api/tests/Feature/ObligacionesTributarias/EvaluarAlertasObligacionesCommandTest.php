<?php

namespace Tests\Feature\ObligacionesTributarias;

use App\Notificaciones\Models\Notificacion;
use App\ObligacionesTributarias\Models\AlertaObligacionTributaria;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesObligacionesTributariasFixtures;
use Tests\TestCase;

class EvaluarAlertasObligacionesCommandTest extends TestCase
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

    public function test_genera_alerta_y_notifica_al_responsable_cuando_entra_en_la_ventana(): void
    {
        $admin = $this->actingAsAdmin();
        $tipo = $this->crearTipoObligacion(['dias_alerta_previa' => 5]);
        $obligacion = $this->crearObligacion($tipo, ['fecha_limite' => '2026-10-15', 'responsable_id' => $admin->id]);
        $this->travelTo(now()->setDate(2026, 10, 12));

        $this->artisan('obligaciones_tributarias:evaluar-alertas')->assertSuccessful();

        $this->assertDatabaseCount('alertas_obligaciones_tributarias', 1);
        $alerta = AlertaObligacionTributaria::first();
        $this->assertSame($obligacion->id, $alerta->obligacion_id);
        $this->assertNotNull($alerta->notificacion_id);
        $this->assertDatabaseHas('notificaciones', [
            'id' => $alerta->notificacion_id,
            'usuario_id' => $admin->id,
            'tipo' => 'obligacion_tributaria_por_vencer',
        ]);
    }

    public function test_no_duplica_la_alerta_en_una_segunda_corrida(): void
    {
        $admin = $this->actingAsAdmin();
        $tipo = $this->crearTipoObligacion(['dias_alerta_previa' => 5]);
        $this->crearObligacion($tipo, ['fecha_limite' => '2026-10-15', 'responsable_id' => $admin->id]);
        $this->travelTo(now()->setDate(2026, 10, 12));

        $this->artisan('obligaciones_tributarias:evaluar-alertas');
        $this->artisan('obligaciones_tributarias:evaluar-alertas');

        $this->assertDatabaseCount('alertas_obligaciones_tributarias', 1);
        $this->assertDatabaseCount('notificaciones', 1);
    }

    public function test_genera_la_alerta_sin_notificar_cuando_no_hay_responsable(): void
    {
        $this->actingAsAdmin();
        $tipo = $this->crearTipoObligacion(['dias_alerta_previa' => 5]);
        $this->crearObligacion($tipo, ['fecha_limite' => '2026-10-15']);
        $this->travelTo(now()->setDate(2026, 10, 12));

        $this->artisan('obligaciones_tributarias:evaluar-alertas');

        $this->assertDatabaseCount('alertas_obligaciones_tributarias', 1);
        $this->assertSame(0, Notificacion::count());
    }

    public function test_no_alerta_una_obligacion_fuera_de_la_ventana(): void
    {
        $this->actingAsAdmin();
        $tipo = $this->crearTipoObligacion(['dias_alerta_previa' => 5]);
        $this->crearObligacion($tipo, ['fecha_limite' => '2026-12-31']);
        $this->travelTo(now()->setDate(2026, 10, 12));

        $this->artisan('obligaciones_tributarias:evaluar-alertas');

        $this->assertDatabaseCount('alertas_obligaciones_tributarias', 0);
    }

    public function test_no_alerta_una_obligacion_ya_cumplida(): void
    {
        $this->actingAsAdmin();
        $tipo = $this->crearTipoObligacion(['dias_alerta_previa' => 5]);
        $this->crearObligacion($tipo, ['fecha_limite' => '2026-10-15', 'estado' => 'cumplida']);
        $this->travelTo(now()->setDate(2026, 10, 12));

        $this->artisan('obligaciones_tributarias:evaluar-alertas');

        $this->assertDatabaseCount('alertas_obligaciones_tributarias', 0);
    }

    public function test_alerta_tambien_una_obligacion_ya_vencida(): void
    {
        $admin = $this->actingAsAdmin();
        $tipo = $this->crearTipoObligacion(['dias_alerta_previa' => 5]);
        $this->crearObligacion($tipo, ['fecha_limite' => '2026-10-01', 'responsable_id' => $admin->id]);
        $this->travelTo(now()->setDate(2026, 10, 12));

        $this->artisan('obligaciones_tributarias:evaluar-alertas');

        $alerta = AlertaObligacionTributaria::first();
        $this->assertNotNull($alerta);
        $this->assertLessThan(0, $alerta->dias_restantes);
    }

    /** Explícito por empresa: el comando no tiene sesión, así que no debe depender de Auth::user(). */
    public function test_respeta_el_empresa_id_explicito_de_cada_empresa(): void
    {
        $adminA = $this->actingAsAdmin();
        $tipoA = $this->crearTipoObligacion(['dias_alerta_previa' => 5]);
        $this->crearObligacion($tipoA, ['fecha_limite' => '2026-10-15', 'responsable_id' => $adminA->id]);

        $adminB = $this->actingAsAdmin();
        $tipoB = $this->crearTipoObligacion(['dias_alerta_previa' => 5]);
        $this->crearObligacion($tipoB, ['fecha_limite' => '2026-10-16', 'responsable_id' => $adminB->id]);

        $this->travelTo(now()->setDate(2026, 10, 12));
        $this->app['auth']->forgetGuards();

        $this->artisan('obligaciones_tributarias:evaluar-alertas');

        $this->assertDatabaseCount('alertas_obligaciones_tributarias', 2);
        $this->assertSame(
            [$adminA->empresa_id, $adminB->empresa_id],
            DB::table('alertas_obligaciones_tributarias')->orderBy('id')->pluck('empresa_id')->all(),
        );
    }
}
