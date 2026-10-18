<?php

namespace Tests\Feature\Importacion;

use App\Contabilidad\Enums\EventoContable;
use App\Contabilidad\Models\PlanCuenta;
use App\Creditos\Models\Credito;
use App\Creditos\Models\Cuota;
use App\Usuarios\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\Concerns\ImportaArchivos;
use Tests\TestCase;

/** Saldos iniciales y asientos de la importación no cuentan dos veces la misma cartera, en ningún orden de carga. */
class ImportacionDobleConteoCarteraTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesContabilidadFixtures;
    use ImportaArchivos;
    use RefreshDatabase;

    private const CLIENTES = "Cédula;Nombre completo;Teléfono;Correo electrónico;Empresa (nómina);Dirección;Barrio;Ciudad;Fecha de vínculo (si se conoce);Observaciones\n";

    private const CREDITOS = "Código de crédito;Cédula cliente;Fecha de desembolso;Valor desembolsado (capital);Tasa de interés mensual (%);Plazo (# cuotas);Periodicidad (Mensual/Quincenal);Cuota fija (si ya la calcularon);¿Es refinanciación? (Sí/No);Código del crédito anterior (si refinancia);Saldo refinanciado;Estado actual;Observaciones\n";

    private const PAGOS = "Código de crédito;Fecha de pago;Valor pagado;Tipo (Cuota completa/Abono parcial);Número de cuota a la que aplica (si se sabe);Cuenta/caja receptora;Registrado por;Observaciones\n";

    private const SALDOS = "Fecha de corte,Cuenta PUC,Saldo débito,Saldo crédito\n";

    private const CON_ASIENTO = ['generar_asiento' => true];

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararImportaciones();
    }

    private function reglas(): void
    {
        $cartera = $this->crearCuenta(['codigo' => '133005', 'nombre' => 'Cartera de créditos']);
        $caja = $this->crearCuenta(['codigo' => '110505', 'nombre' => 'Caja']);
        $ingreso = $this->crearCuenta(['codigo' => '421005', 'nombre' => 'Intereses']);
        $tipo = $this->crearTipoComprobante();
        $this->crearRegla(EventoContable::DesembolsoCredito, $cartera, $caja, $tipo);
        $this->crearRegla(EventoContable::RecaudoCapital, $caja, $cartera, $tipo);
        $this->crearRegla(EventoContable::RecaudoInteres, $caja, $ingreso, $tipo);
    }

    private function saldoDeCartera(Usuario $admin, string $fechaCorte): void
    {
        DB::table('saldos_apertura')->insert([
            'empresa_id' => $admin->empresa_id,
            'cuenta_id' => PlanCuenta::query()->where('codigo', '133005')->value('id'),
            'fecha_corte' => $fechaCorte,
            'debito' => 200000000,
            'credito' => 0,
            'creado_en' => now(),
            'ultima_actualizacion' => now(),
        ]);
    }

    private function creditoSinAsiento(): void
    {
        $this->importarCompleto('clientes', self::CLIENTES."1001;Ana Pérez;;;;;;;;\n");
        $this->importarCompleto('creditos', self::CREDITOS."CR-1;1001;01/10/2023;2000000;2,5;4;mensual;;No;;;;\n", ['generar_asiento' => false], 'creditos.csv', self::CON_ASIENTO);
    }

    private function pagarPrimeraCuota(string $fecha): int
    {
        $valorCuota = Cuota::query()->whereHas('credito', fn ($q) => $q->where('codigo', 'CR-1'))->where('numero_cuota', 1)->value('valor')->aDecimalString();

        return $this->importarCompleto('pagos', self::PAGOS."CR-1;{$fecha};{$valorCuota};Cuota completa;;;;\n", self::CON_ASIENTO, 'pagos.csv', self::CON_ASIENTO);
    }

    public function test_un_credito_desembolsado_hasta_el_corte_no_genera_asiento_y_uno_posterior_si(): void
    {
        $admin = $this->actingAsAdmin();
        $this->reglas();
        $this->saldoDeCartera($admin, '2023-10-15');
        $this->importarCompleto('clientes', self::CLIENTES."1001;Ana Pérez;;;;;;;;\n");

        $id = $this->importarCompleto(
            'creditos',
            self::CREDITOS."CR-1;1001;01/10/2023;2000000;2,5;4;mensual;;No;;;;\nCR-2;1001;02/11/2023;1000000;2,5;4;mensual;;No;;;;\n",
            self::CON_ASIENTO,
            'creditos.csv',
            self::CON_ASIENTO,
        );

        $this->assertSame(2, $this->importacion($id)->filas_aceptadas);
        $this->assertSame(
            [Credito::query()->where('codigo', 'CR-2')->value('id')],
            DB::table('comprobantes')->where('modulo_origen', 'creditos')->pluck('origen_id')->all(),
        );
        $avisos = array_filter($this->erroresDe($id), fn (array $error) => $error['severidad'] === 'aviso' && $error['fila'] === 2);
        $this->assertStringContainsString('corte 2023-10-15', implode(' ', array_column($avisos, 'motivo')));
    }

    public function test_un_pago_hasta_el_corte_se_registra_sin_asiento_y_avisa(): void
    {
        $admin = $this->actingAsAdmin();
        $this->reglas();
        $this->creditoSinAsiento();
        $this->saldoDeCartera($admin, '2023-11-30');

        $id = $this->pagarPrimeraCuota('14/11/2023');

        $this->assertSame(1, $this->importacion($id)->filas_aceptadas);
        $this->assertDatabaseCount('pagos', 1);
        $this->assertDatabaseMissing('comprobantes', ['modulo_origen' => 'pagos']);
        $this->assertStringContainsString('ya incluyen este pago', implode(' ', array_column($this->erroresDe($id), 'motivo')));
    }

    public function test_un_pago_posterior_al_corte_genera_su_asiento(): void
    {
        $admin = $this->actingAsAdmin();
        $this->reglas();
        $this->creditoSinAsiento();
        $this->saldoDeCartera($admin, '2023-10-31');

        $id = $this->pagarPrimeraCuota('14/11/2023');

        $this->assertSame(1, $this->importacion($id)->filas_aceptadas);
        $this->assertDatabaseHas('comprobantes', ['modulo_origen' => 'pagos']);
    }

    public function test_un_saldo_de_una_cuenta_con_asientos_hasta_su_corte_se_rechaza(): void
    {
        $this->actingAsAdmin();
        $this->reglas();
        $this->importarCompleto('clientes', self::CLIENTES."1001;Ana Pérez;;;;;;;;\n");
        $this->importarCompleto('creditos', self::CREDITOS."CR-1;1001;01/10/2023;2000000;2,5;4;mensual;;No;;;;\n", self::CON_ASIENTO, 'creditos.csv', self::CON_ASIENTO);

        $id = $this->importarCompleto('saldos_iniciales', self::SALDOS
            ."31/12/2023,133005,2000000,0\n"
            ."31/12/2023,421005,0,500000\n"
            ."30/09/2023,110505,800000,0\n");

        $this->assertSame(2, $this->importacion($id)->filas_aceptadas);
        $this->assertSame([2], array_column($this->erroresDe($id), 'fila'));
        $this->assertStringContainsString('133005 ya tiene asientos desde el 2023-10-01', implode(' ', $this->motivosDeRechazo($id)));
        $this->assertDatabaseMissing('saldos_apertura', ['cuenta_id' => PlanCuenta::query()->where('codigo', '133005')->value('id')]);
    }

    public function test_los_asientos_de_otra_empresa_no_bloquean_los_saldos(): void
    {
        $this->actingAsAdmin();
        $this->reglas();
        $this->importarCompleto('clientes', self::CLIENTES."1001;Ana Pérez;;;;;;;;\n");
        $this->importarCompleto('creditos', self::CREDITOS."CR-1;1001;01/10/2023;2000000;2,5;4;mensual;;No;;;;\n", self::CON_ASIENTO, 'creditos.csv', self::CON_ASIENTO);

        $this->actingAsAdmin();
        $this->crearCuenta(['codigo' => '133005', 'nombre' => 'Cartera de créditos']);

        $id = $this->importarCompleto('saldos_iniciales', self::SALDOS."31/12/2023,133005,2000000,0\n");

        $this->assertSame(1, $this->importacion($id)->filas_aceptadas);
    }
}
