<?php

namespace Tests\Feature\Importacion;

use App\Contabilidad\Enums\NaturalezaCuenta;
use App\Contabilidad\Enums\TipoCuenta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\Concerns\ImportaArchivos;
use Tests\TestCase;

class ImportarPlanCuentasYSaldosTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesContabilidadFixtures;
    use CreatesCreditFixtures;
    use ImportaArchivos;
    use RefreshDatabase;

    public function test_importa_cuentas_validas_y_rechaza_las_invalidas_con_mensajes_legibles(): void
    {
        $this->actingAsAdmin();
        $this->prepararImportaciones();

        $id = $this->importarCompleto('plan_cuentas', "codigo,nombre,naturaleza,tipo,nivel\n"
            ."1105,Caja,debito,activo,2\n"
            ."1110,Bancos,deudora,activo,2\n"
            ."1115,Cartera,debito,inventario,2\n"
            ."1105,Caja repetida,debito,activo,2\n");

        $importacion = $this->importacion($id);
        $this->assertSame(1, $importacion->filas_aceptadas);
        $this->assertSame(3, $importacion->filas_rechazadas);
        $this->assertDatabaseHas('plan_cuentas', ['codigo' => '1105']);
        $this->assertDatabaseCount('plan_cuentas', 1);

        $motivos = implode(' ', $this->motivosDeRechazo($id));
        $this->assertStringNotContainsString('App\\', $motivos);
        $this->assertStringNotContainsString('SQLSTATE', $motivos);
        $this->assertContains(3, array_column($this->erroresDe($id), 'fila'));
    }

    public function test_rechaza_el_archivo_que_no_trae_las_columnas_obligatorias_antes_de_encolar(): void
    {
        $this->actingAsAdmin();
        $this->prepararImportaciones();

        $this->postJson('/api/v1/importaciones', [
            'tipo' => 'plan_cuentas',
            'archivo' => UploadedFile::fake()->createWithContent('cuentas.csv', "codigo,nombre\n1105,Caja\n"),
        ])->assertUnprocessable()->assertJsonValidationErrors('archivo');
    }

    public function test_saldos_sin_columna_de_fecha_se_rechazan_antes_de_encolar(): void
    {
        $this->actingAsAdmin();
        $this->prepararImportaciones();
        $this->importarCompleto('plan_cuentas', "codigo,nombre,naturaleza,tipo\n1105,Caja,debito,activo\n");

        $this->postJson('/api/v1/importaciones', [
            'tipo' => 'saldos_iniciales',
            'archivo' => UploadedFile::fake()->createWithContent('saldos.csv', "Cuenta PUC,Saldo débito,Saldo crédito\n1105,1000,0\n"),
        ])->assertUnprocessable()->assertJsonValidationErrors('archivo');

        $this->assertDatabaseCount('saldos_apertura', 0);
    }

    public function test_saldos_con_fecha_en_formato_no_reconocido_se_rechazan_con_mensaje_legible(): void
    {
        $this->actingAsAdmin();
        $this->prepararImportaciones();
        $this->importarCompleto('plan_cuentas', "codigo,nombre,naturaleza,tipo\n1105,Caja,debito,activo\n");

        $id = $this->importarCompleto('saldos_iniciales', "Fecha de corte,Cuenta PUC,Saldo débito,Saldo crédito\n2026.12.31,1105,1000,0\n");

        $importacion = $this->importacion($id);
        $this->assertSame(0, $importacion->filas_aceptadas);
        $this->assertSame(1, $importacion->filas_rechazadas);
        $this->assertStringContainsString('Fecha no reconocida', implode(' ', $this->motivosDeRechazo($id)));
        $this->assertDatabaseCount('saldos_apertura', 0);
    }

    public function test_importa_saldos_validos_y_no_duplica_al_resubirlos(): void
    {
        $this->actingAsAdmin();
        $this->prepararImportaciones();
        $this->importarCompleto('plan_cuentas', "codigo,nombre,naturaleza,tipo\n1105,Caja,debito,activo\n");

        $csv = "Fecha de corte,Cuenta PUC,Saldo débito,Saldo crédito\n31/12/2026,1105,1000,0\n";
        $primera = $this->importarCompleto('saldos_iniciales', $csv, ['generar_asiento' => false]);
        $segunda = $this->importarCompleto('saldos_iniciales', $csv);

        $this->assertSame(1, $this->importacion($primera)->filas_aceptadas);
        $this->assertSame(0, $this->importacion($segunda)->filas_aceptadas);
        $this->assertStringContainsString('Ya existe un saldo', implode(' ', $this->motivosDeRechazo($segunda)));
        $this->assertDatabaseCount('saldos_apertura', 1);
    }

    public function test_el_codigo_de_cuenta_puede_repetirse_en_otra_empresa(): void
    {
        $this->actingAsAdmin();
        $this->prepararImportaciones();
        $this->importarCompleto('plan_cuentas', "codigo,nombre,naturaleza,tipo\n1105,Caja,debito,activo\n");

        $this->actingAsAdmin();
        $id = $this->importarCompleto('plan_cuentas', "codigo,nombre,naturaleza,tipo\n1105,Caja,debito,activo\n");

        $this->assertSame(1, $this->importacion($id)->filas_aceptadas);
        $this->assertDatabaseCount('plan_cuentas', 2);
    }

    public function test_el_archivo_balanceado_genera_el_comprobante_de_apertura_con_tercero_en_cartera(): void
    {
        $this->actingAsAdmin();
        $this->prepararImportaciones();

        $caja = $this->crearCuenta(['codigo' => '1105', 'nombre' => 'Caja', 'naturaleza' => NaturalezaCuenta::Debito, 'tipo' => TipoCuenta::Activo]);
        $cartera = $this->crearCuenta(['codigo' => '1305', 'nombre' => 'Cartera', 'naturaleza' => NaturalezaCuenta::Debito, 'tipo' => TipoCuenta::Activo]);
        $this->crearCuenta(['codigo' => '3105', 'nombre' => 'Capital', 'naturaleza' => NaturalezaCuenta::Credito, 'tipo' => TipoCuenta::Patrimonio]);
        $tipoComprobante = $this->crearTipoComprobante();
        $cliente = $this->crearCliente();

        $csv = "Fecha de corte,Cuenta PUC,Saldo débito,Saldo crédito,Cédula\n"
            ."31/12/2026,1105,10000,0,\n"
            ."31/12/2026,1305,5000,0,{$cliente->tercero->numero_documento}\n"
            ."31/12/2026,3105,0,15000,\n";

        $id = $this->importarCompleto(
            'saldos_iniciales',
            $csv,
            ['generar_asiento' => true],
            'saldos.csv',
            ['generar_asiento' => true, 'tipo_comprobante_id' => $tipoComprobante->id],
        );

        $importacion = $this->importacion($id);
        $this->assertSame(3, $importacion->filas_aceptadas);
        $this->assertNotNull($importacion->comprobante_id);
        $this->assertDatabaseHas('comprobantes', ['id' => $importacion->comprobante_id, 'modulo_origen' => 'saldos_iniciales', 'estado' => 'contabilizado']);
        $this->assertDatabaseHas('asientos_contables', ['comprobante_id' => $importacion->comprobante_id, 'cuenta_id' => $cartera->id, 'debito' => 500000, 'tercero_id' => $cliente->tercero_id]);
        $this->assertDatabaseHas('asientos_contables', ['comprobante_id' => $importacion->comprobante_id, 'cuenta_id' => $caja->id, 'debito' => 1000000, 'tercero_id' => null]);
        $this->assertDatabaseHas('saldos_apertura', ['cuenta_id' => $cartera->id, 'tercero_id' => $cliente->tercero_id]);
    }

    public function test_el_archivo_descuadrado_no_genera_comprobante_pero_deja_los_saldos_guardados(): void
    {
        $this->actingAsAdmin();
        $this->prepararImportaciones();

        $this->crearCuenta(['codigo' => '1105', 'naturaleza' => NaturalezaCuenta::Debito, 'tipo' => TipoCuenta::Activo]);
        $this->crearCuenta(['codigo' => '3105', 'naturaleza' => NaturalezaCuenta::Credito, 'tipo' => TipoCuenta::Patrimonio]);
        $tipoComprobante = $this->crearTipoComprobante();

        $csv = "Fecha de corte,Cuenta PUC,Saldo débito,Saldo crédito\n"
            ."31/12/2026,1105,10000,0\n"
            ."31/12/2026,3105,0,9000\n";

        $id = $this->importarCompleto(
            'saldos_iniciales',
            $csv,
            ['generar_asiento' => true],
            'saldos.csv',
            ['generar_asiento' => true, 'tipo_comprobante_id' => $tipoComprobante->id],
        );

        $importacion = $this->importacion($id);
        $this->assertSame(2, $importacion->filas_aceptadas);
        $this->assertNull($importacion->comprobante_id);
        $this->assertDatabaseCount('saldos_apertura', 2);
        $this->assertDatabaseCount('comprobantes', 0);

        $motivosEscritura = array_column(array_filter($this->erroresDe($id), fn (array $error) => $error['fase'] === 'escritura'), 'motivo');
        $this->assertStringContainsString('descuadrado', implode(' ', $motivosEscritura));
    }

    public function test_generar_asiento_sin_tipo_de_comprobante_se_rechaza_antes_de_encolar(): void
    {
        $this->actingAsAdmin();
        $this->prepararImportaciones();
        $this->importarCompleto('plan_cuentas', "codigo,nombre,naturaleza,tipo\n1105,Caja,debito,activo\n");

        $this->postJson('/api/v1/importaciones', [
            'tipo' => 'saldos_iniciales',
            'generar_asiento' => '1',
            'archivo' => UploadedFile::fake()->createWithContent('saldos.csv', "Fecha de corte,Cuenta PUC,Saldo débito,Saldo crédito\n31/12/2026,1105,1000,0\n"),
        ])->assertUnprocessable()->assertJsonValidationErrors('tipo_comprobante_id');
    }

    public function test_la_cedula_en_una_cuenta_que_no_es_cartera_se_rechaza(): void
    {
        $this->actingAsAdmin();
        $this->prepararImportaciones();
        $this->crearCuenta(['codigo' => '1105', 'naturaleza' => NaturalezaCuenta::Debito, 'tipo' => TipoCuenta::Activo]);
        $cliente = $this->crearCliente();

        $csv = "Fecha de corte,Cuenta PUC,Saldo débito,Saldo crédito,Cédula\n31/12/2026,1105,10000,0,{$cliente->tercero->numero_documento}\n";

        $id = $this->importarCompleto('saldos_iniciales', $csv);

        $this->assertSame(0, $this->importacion($id)->filas_aceptadas);
        $this->assertStringContainsString('no es de cartera', implode(' ', $this->motivosDeRechazo($id)));
    }
}
