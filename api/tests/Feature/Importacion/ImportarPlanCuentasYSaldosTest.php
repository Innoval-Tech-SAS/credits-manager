<?php

namespace Tests\Feature\Importacion;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\ImportaArchivos;
use Tests\TestCase;

class ImportarPlanCuentasYSaldosTest extends TestCase
{
    use ActsAsAdmin;
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
        $primera = $this->importarCompleto('saldos_iniciales', $csv);
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
}
