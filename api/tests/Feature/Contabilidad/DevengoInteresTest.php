<?php

namespace Tests\Feature\Contabilidad;

use App\Contabilidad\Enums\EventoContable;
use App\Contabilidad\Enums\NaturalezaCuenta;
use App\Contabilidad\Enums\TipoCuenta;
use App\Creditos\Enums\EstadoCuota;
use App\Creditos\Models\Cuota;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\TestCase;

class DevengoInteresTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesContabilidadFixtures;
    use CreatesCreditFixtures;
    use RefreshDatabase;

    private function tarea(string $nombre): Event
    {
        $tarea = collect(app(Schedule::class)->events())->first(fn (Event $evento) => $evento->description === $nombre);

        $this->assertNotNull($tarea, "La tarea {$nombre} no está programada.");

        return $tarea;
    }

    public function test_programa_el_devengo_el_dia_1_de_cada_mes_sin_solaparse(): void
    {
        $tarea = $this->tarea('contabilidad:devengar-intereses');

        $this->assertSame('0 2 1 * *', $tarea->expression);
        $this->assertTrue($tarea->withoutOverlapping);
    }

    public function test_devenga_la_cuota_vencida_con_tercero_y_marca_la_cuota(): void
    {
        $this->actingAsAdmin();
        $interes = $this->crearCuenta(['naturaleza' => NaturalezaCuenta::Debito, 'tipo' => TipoCuenta::Activo]);
        $ingreso = $this->crearCuenta(['naturaleza' => NaturalezaCuenta::Credito, 'tipo' => TipoCuenta::Ingreso]);
        $this->crearRegla(EventoContable::DevengoInteres, $interes, $ingreso);

        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente);
        $cuota = $this->crearCuota($credito, ['fecha_vencimiento' => now()->subDays(5)->toDateString(), 'valor_interes' => 50, 'estado' => EstadoCuota::Pendiente]);

        $this->artisan('contabilidad:devengar-intereses')->assertSuccessful();

        $cuota->refresh();
        $this->assertNotNull($cuota->interes_devengado_en);

        $this->assertDatabaseHas('comprobantes', ['modulo_origen' => 'devengo_interes', 'estado' => 'contabilizado', 'total_debito' => 5000, 'total_credito' => 5000]);
        $this->assertDatabaseHas('asientos_contables', ['cuenta_id' => $interes->id, 'debito' => 5000, 'tercero_id' => $cliente->tercero_id]);
        $this->assertDatabaseHas('asientos_contables', ['cuenta_id' => $ingreso->id, 'credito' => 5000, 'tercero_id' => null]);
    }

    public function test_no_devenga_dos_veces_la_misma_cuota(): void
    {
        $this->actingAsAdmin();
        $interes = $this->crearCuenta(['naturaleza' => NaturalezaCuenta::Debito, 'tipo' => TipoCuenta::Activo]);
        $ingreso = $this->crearCuenta(['naturaleza' => NaturalezaCuenta::Credito, 'tipo' => TipoCuenta::Ingreso]);
        $this->crearRegla(EventoContable::DevengoInteres, $interes, $ingreso);

        $credito = $this->crearCredito($this->crearCliente());
        $this->crearCuota($credito, ['fecha_vencimiento' => now()->subDays(5)->toDateString(), 'valor_interes' => 50]);

        $this->artisan('contabilidad:devengar-intereses')->assertSuccessful();
        $this->artisan('contabilidad:devengar-intereses')->assertSuccessful();

        $this->assertDatabaseCount('comprobantes', 1);
    }

    public function test_no_devenga_un_credito_con_90_dias_de_mora_o_mas(): void
    {
        $this->actingAsAdmin();
        $interes = $this->crearCuenta(['naturaleza' => NaturalezaCuenta::Debito, 'tipo' => TipoCuenta::Activo]);
        $ingreso = $this->crearCuenta(['naturaleza' => NaturalezaCuenta::Credito, 'tipo' => TipoCuenta::Ingreso]);
        $this->crearRegla(EventoContable::DevengoInteres, $interes, $ingreso);

        $credito = $this->crearCredito($this->crearCliente());
        // La cuota más vieja en mora fija los días de mora del crédito: 90 días exactos suspende el devengo.
        $cuota = $this->crearCuota($credito, [
            'fecha_vencimiento' => now()->subDays(90)->toDateString(),
            'valor_interes' => 50,
            'estado' => EstadoCuota::Vencida,
        ]);

        $this->artisan('contabilidad:devengar-intereses')->assertSuccessful();

        $this->assertNull($cuota->refresh()->interes_devengado_en);
        $this->assertDatabaseCount('comprobantes', 0);
    }

    public function test_sin_regla_configurada_no_genera_nada_ni_falla(): void
    {
        $this->actingAsAdmin();
        $credito = $this->crearCredito($this->crearCliente());
        $this->crearCuota($credito, ['fecha_vencimiento' => now()->subDays(5)->toDateString(), 'valor_interes' => 50]);

        $this->artisan('contabilidad:devengar-intereses')->assertSuccessful();

        $this->assertDatabaseCount('comprobantes', 0);
        $this->assertDatabaseCount('cuotas', 1);
        $this->assertNull(Cuota::first()->interes_devengado_en);
    }

    public function test_cada_empresa_devenga_solo_sus_propias_cuotas(): void
    {
        $empresaA = $this->crearEmpresaDePrueba();
        $this->actingAsAdmin($empresaA);
        $interesA = $this->crearCuenta(['naturaleza' => NaturalezaCuenta::Debito, 'tipo' => TipoCuenta::Activo]);
        $ingresoA = $this->crearCuenta(['naturaleza' => NaturalezaCuenta::Credito, 'tipo' => TipoCuenta::Ingreso]);
        $this->crearRegla(EventoContable::DevengoInteres, $interesA, $ingresoA);
        $creditoA = $this->crearCredito($this->crearCliente());
        $cuotaA = $this->crearCuota($creditoA, ['fecha_vencimiento' => now()->subDays(5)->toDateString(), 'valor_interes' => 50]);

        $empresaB = $this->crearEmpresaDePrueba();
        $this->actingAsAdmin($empresaB);
        $creditoB = $this->crearCredito($this->crearCliente());
        $cuotaB = $this->crearCuota($creditoB, ['fecha_vencimiento' => now()->subDays(5)->toDateString(), 'valor_interes' => 50]);
        // Empresa B no configuró la regla: debe quedar sin devengar y sin tocar lo de A.

        $this->salirDeSesion();
        $this->artisan('contabilidad:devengar-intereses')->assertSuccessful();

        $this->assertNotNull($cuotaA->refresh()->interes_devengado_en);
        $this->assertNull($cuotaB->refresh()->interes_devengado_en);
        $this->assertDatabaseCount('comprobantes', 1);
    }
}
