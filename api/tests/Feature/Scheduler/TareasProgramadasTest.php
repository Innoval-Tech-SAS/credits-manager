<?php

namespace Tests\Feature\Scheduler;

use App\Creditos\Enums\EstadoCuota;
use App\Creditos\Models\Cuota;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\AfirmaAuditoria;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\TestCase;

class TareasProgramadasTest extends TestCase
{
    use ActsAsAdmin;
    use AfirmaAuditoria;
    use CreatesCreditFixtures;
    use RefreshDatabase;

    private function tarea(string $nombre): Event
    {
        $tarea = collect(app(Schedule::class)->events())->first(fn (Event $evento) => $evento->description === $nombre);

        $this->assertNotNull($tarea, "La tarea {$nombre} no está programada.");

        return $tarea;
    }

    /** @return array<int, int> credito_id por empresa_id, con una cuota vencida cada uno */
    private function crearCuotasVencidasEnDosEmpresas(): array
    {
        $creditosPorEmpresa = [];

        foreach ([$this->crearEmpresaDePrueba(), $this->crearEmpresaDePrueba()] as $empresa) {
            $this->actingAsAdmin($empresa);
            $credito = $this->crearCredito($this->crearCliente());
            $this->crearCuota($credito, ['fecha_vencimiento' => now()->subDays(3)->toDateString()]);
            $creditosPorEmpresa[$empresa->id] = $credito->id;
        }

        $this->salirDeSesion();

        return $creditosPorEmpresa;
    }

    public function test_programa_la_mora_diaria_a_las_00_05_sin_solaparse(): void
    {
        $tarea = $this->tarea('cuotas:actualizar-vencidas');

        $this->assertSame('5 0 * * *', $tarea->expression);
        $this->assertTrue($tarea->withoutOverlapping);
    }

    public function test_programa_la_limpieza_de_exportaciones_a_las_03_00_sin_solaparse(): void
    {
        $tarea = $this->tarea('exportaciones:limpiar');

        $this->assertSame('0 3 * * *', $tarea->expression);
        $this->assertTrue($tarea->withoutOverlapping);
    }

    public function test_a_las_00_05_el_scheduler_marca_la_mora_de_todas_las_empresas_sin_sesion(): void
    {
        $creditosPorEmpresa = $this->crearCuotasVencidasEnDosEmpresas();

        $this->travelTo(now()->startOfDay()->addMinutes(5));
        $this->artisan('schedule:run')->assertSuccessful();

        $this->assertSame(2, Cuota::where('estado', EstadoCuota::Vencida)->count());

        $eventos = collect($this->eventosDeAuditoria('modificar', 'cartera'))->keyBy('empresa_id');
        $this->assertCount(2, $eventos);

        foreach ($creditosPorEmpresa as $empresaId => $creditoId) {
            $this->assertSame([$creditoId], $eventos[$empresaId]['valores_nuevos']['creditos'], 'cada empresa solo ve sus propios créditos');
            $this->assertNull($eventos[$empresaId]['autor']['usuario_id']);
        }
    }

    public function test_fuera_de_la_hora_programada_el_scheduler_no_toca_la_mora(): void
    {
        $this->crearCuotasVencidasEnDosEmpresas();

        $this->travelTo(now()->startOfDay()->addHours(12));
        $this->artisan('schedule:run')->assertSuccessful();

        $this->assertSame(0, Cuota::where('estado', EstadoCuota::Vencida)->count());
        $this->assertSinEventosDeAuditoria('modificar', 'cartera');
    }

    public function test_al_correr_la_tarea_de_mora_queda_una_ejecucion_ok_en_el_historial(): void
    {
        $this->crearCuotasVencidasEnDosEmpresas();

        $this->travelTo(now()->startOfDay()->addMinutes(5));
        $this->artisan('schedule:run')->assertSuccessful();

        $this->assertDatabaseHas('ejecuciones_tareas_programadas', [
            'tarea_clave' => 'cuotas:actualizar-vencidas',
            'resultado' => 'ok',
        ]);
    }

    public function test_pausar_la_tarea_desde_la_tabla_evita_que_el_scheduler_la_corra(): void
    {
        $this->crearCuotasVencidasEnDosEmpresas();
        DB::table('tareas_programadas')->where('clave', 'cuotas:actualizar-vencidas')->update(['activa' => false]);

        $this->travelTo(now()->startOfDay()->addMinutes(5));
        $this->artisan('schedule:run')->assertSuccessful();

        $this->assertSame(0, Cuota::where('estado', EstadoCuota::Vencida)->count());
        $this->assertSinEventosDeAuditoria('modificar', 'cartera');
        $this->assertDatabaseMissing('ejecuciones_tareas_programadas', ['tarea_clave' => 'cuotas:actualizar-vencidas']);
    }
}
