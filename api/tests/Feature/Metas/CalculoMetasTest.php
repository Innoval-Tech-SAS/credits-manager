<?php

namespace Tests\Feature\Metas;

use App\Creditos\Enums\EstadoCredito;
use App\Empresas\Models\Empresa;
use App\Metas\Enums\TipoMeta;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\Concerns\CreatesMetasFixtures;
use Tests\TestCase;

class CalculoMetasTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesCreditFixtures;
    use CreatesMetasFixtures;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** Un crédito normal de 1.000.000 y una refinanciación de 1.500.000 que tomó 400.000 de saldo, ambos en octubre. */
    private function escenarioDeRefinanciacion(): void
    {
        $cliente = $this->crearCliente();
        $this->crearCredito($cliente, ['valor_prestamo' => 1000000, 'desembolsado_en' => '2026-10-03 10:00:00']);

        $original = $this->crearCredito($cliente, [
            'valor_prestamo' => 800000,
            'desembolsado_en' => '2026-08-01 10:00:00',
            'estado' => EstadoCredito::Refinanciado,
        ]);

        $this->crearCredito($cliente, [
            'valor_prestamo' => 1500000,
            'saldo_refinanciado' => 400000,
            'credito_origen_id' => $original->id,
            'desembolsado_en' => '2026-10-05 10:00:00',
        ]);
    }

    /** Ejecutado de la meta de empresa, tal como lo devuelve la API. */
    private function ejecutadoDeEmpresa(string $periodo, string $tipo): string
    {
        $meta = collect($this->getJson("/api/v1/metas?periodo={$periodo}")->assertOk()->json('metas'))
            ->firstWhere('tipo', $tipo);

        return $meta['empresa']['ejecutado'];
    }

    public function test_colocacion_con_refinanciaciones_cambia_segun_el_modo_configurado(): void
    {
        $this->actingAsAdmin();
        $this->escenarioDeRefinanciacion();
        $this->crearMeta('2026-10', TipoMeta::Colocacion, 3000000);
        $this->simularHoy('2026-10-12 10:00:00');

        $esperados = [
            'excluir' => '1000000.00',
            'solo_dinero_nuevo' => '2100000.00',
            'total' => '2500000.00',
        ];

        foreach ($esperados as $modo => $esperado) {
            $this->configurarMetas('modo_colocacion_refinanciaciones', $modo);

            $this->assertSame($esperado, $this->ejecutadoDeEmpresa('2026-10', 'colocacion'), "Modo {$modo}");
        }
    }

    public function test_recaudo_respeta_la_base_configurada(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente, ['valor_prestamo' => 5000000]);
        $this->crearPago($cliente, $credito, 500000, '2026-10-08 09:00:00', 400000, 100000);
        $this->crearMeta('2026-10', TipoMeta::Recaudo, 1000000);
        $this->simularHoy('2026-10-12 10:00:00');

        $esperados = [
            'total' => '500000.00',
            'solo_capital' => '400000.00',
            'solo_interes' => '100000.00',
        ];

        foreach ($esperados as $base => $esperado) {
            $this->configurarMetas('base_recaudo', $base);

            $this->assertSame($esperado, $this->ejecutadoDeEmpresa('2026-10', 'recaudo'), "Base {$base}");
        }
    }

    public function test_la_atribucion_sigue_al_asesor_vigente_en_la_fecha_del_hecho(): void
    {
        $admin = $this->actingAsAdmin();
        $empresa = Empresa::findOrFail($admin->empresa_id);
        $ana = $this->crearAsesor($empresa, 'Ana');
        $beto = $this->crearAsesor($empresa, 'Beto');

        $cliente = $this->crearCliente();
        $this->asignarAsesor($cliente, $ana, '2026-10-01 08:00:00');
        $this->asignarAsesor($cliente, $beto, '2026-10-15 12:00:00');
        $credito = $this->crearCredito($cliente, ['valor_prestamo' => 5000000]);

        $this->crearPago($cliente, $credito, 300000, '2026-10-10 09:00:00', 300000, 0);
        $this->crearPago($cliente, $credito, 200000, '2026-10-20 09:00:00', 200000, 0);

        $meta = $this->crearMeta('2026-10', TipoMeta::Recaudo, 1000000);
        $this->distribuirMeta($meta, $ana, 500000);
        $this->distribuirMeta($meta, $beto, 500000);
        $this->simularHoy('2026-10-25 10:00:00');

        $asesores = collect($this->getJson('/api/v1/metas?periodo=2026-10')->assertOk()->json('metas.0.asesores'))
            ->keyBy('nombre');

        $this->assertSame('300000.00', $asesores['Ana']['cumplimiento']['ejecutado']);
        $this->assertSame('200000.00', $asesores['Beto']['cumplimiento']['ejecutado']);
    }

    public function test_estado_en_riesgo_cuando_el_avance_va_por_debajo_de_los_dias_transcurridos(): void
    {
        $this->actingAsAdmin();
        $this->escenarioDeMeta(100000, 1000000);
        $this->simularHoy('2026-10-12 10:00:00');

        $this->assertSame('en_riesgo', $this->estadoDeEmpresa('2026-10', 'colocacion'));
    }

    public function test_estado_en_curso_dentro_de_la_tolerancia(): void
    {
        $this->actingAsAdmin();
        $this->escenarioDeMeta(350000, 1000000);
        $this->simularHoy('2026-10-12 10:00:00');

        $this->assertSame('en_curso', $this->estadoDeEmpresa('2026-10', 'colocacion'));
    }

    public function test_estado_alcanzada_al_llegar_al_cien_por_ciento_en_periodo_abierto(): void
    {
        $this->actingAsAdmin();
        $this->escenarioDeMeta(1000000, 1000000);
        $this->simularHoy('2026-10-12 10:00:00');

        $this->assertSame('alcanzada', $this->estadoDeEmpresa('2026-10', 'colocacion'));
    }

    public function test_periodo_cerrado_queda_cumplida_al_cien_por_ciento(): void
    {
        $this->actingAsAdmin();
        $this->escenarioDeMeta(1000000, 1000000, '2026-09');
        $this->cerrarSeptiembre();

        $this->assertSame('cumplida', $this->estadoDeEmpresa('2026-09', 'colocacion'));
    }

    public function test_periodo_cerrado_queda_cumplida_parcial_entre_el_minimo_y_el_cien(): void
    {
        $this->actingAsAdmin();
        $this->escenarioDeMeta(950000, 1000000, '2026-09');
        $this->cerrarSeptiembre();

        $this->assertSame('cumplida_parcial', $this->estadoDeEmpresa('2026-09', 'colocacion'));
    }

    public function test_periodo_cerrado_queda_incumplida_por_debajo_del_minimo_parcial(): void
    {
        $this->actingAsAdmin();
        $this->escenarioDeMeta(800000, 1000000, '2026-09');
        $this->cerrarSeptiembre();

        $this->assertSame('incumplida', $this->estadoDeEmpresa('2026-09', 'colocacion'));
    }

    /** Meta de colocación de octubre y un crédito de `$colocado` pesos desembolsado en el período. */
    private function escenarioDeMeta(int $colocado, int $objetivo, string $periodo = '2026-10'): void
    {
        $fecha = $periodo === '2026-10' ? '2026-10-03 10:00:00' : '2026-09-10 10:00:00';

        $this->crearCredito($this->crearCliente(), ['valor_prestamo' => $colocado, 'desembolsado_en' => $fecha]);
        $this->crearMeta($periodo, TipoMeta::Colocacion, $objetivo);
    }

    private function cerrarSeptiembre(): void
    {
        $this->simularHoy('2026-10-01 10:00:00');
        $this->artisan('metas:evaluar')->assertSuccessful();
    }

    private function estadoDeEmpresa(string $periodo, string $tipo): string
    {
        $meta = collect($this->getJson("/api/v1/metas?periodo={$periodo}")->assertOk()->json('metas'))
            ->firstWhere('tipo', $tipo);

        return $meta['empresa']['estado'];
    }
}
