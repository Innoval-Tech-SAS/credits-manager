<?php

namespace Tests\Feature\Contabilidad;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class ImportarCuentasYSaldosMigracionAperturaTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    private function importar(string $tipo, string $encabezado, array $filas): array
    {
        $csv = $encabezado."\n".implode("\n", $filas)."\n";

        return $this->postJson('/api/v1/contabilidad/migraciones-apertura', [
            'tipo' => $tipo,
            'archivo' => UploadedFile::fake()->createWithContent($tipo.'.csv', $csv),
        ])->assertCreated()->json('migracion');
    }

    public function test_importa_cuentas_validas_y_rechaza_las_invalidas_con_mensajes_legibles(): void
    {
        $this->actingAsAdmin();

        $migracion = $this->importar('plan_cuentas', 'codigo,nombre,naturaleza,tipo,nivel', [
            '1105,Caja,debito,activo,2',
            '1110,Bancos,deudora,activo,2',
            '1115,Cartera,debito,inventario,2',
            '1105,Caja repetida,debito,activo,2',
        ]);

        $this->assertSame(1, $migracion['registros_aceptados']);
        $this->assertSame(3, $migracion['registros_rechazados']);
        $this->assertDatabaseHas('plan_cuentas', ['codigo' => '1105']);
        $this->assertDatabaseCount('plan_cuentas', 1);

        $errores = implode(' ', $migracion['detalle_errores']);
        $this->assertStringNotContainsString('App\\', $errores);
        $this->assertStringNotContainsString('backing value', $errores);
        $this->assertStringNotContainsString('SQLSTATE', $errores);
        $this->assertStringContainsString('Fila 3', $errores);
    }

    public function test_rechaza_cuentas_a_las_que_les_falta_una_columna_sin_mensajes_de_php(): void
    {
        $this->actingAsAdmin();

        $migracion = $this->importar('plan_cuentas', 'codigo,nombre', ['1105,Caja']);

        $this->assertSame(0, $migracion['registros_aceptados']);
        $this->assertSame(1, $migracion['registros_rechazados']);
        $this->assertStringNotContainsString('Undefined array key', implode(' ', $migracion['detalle_errores']));
    }

    public function test_saldos_sin_columna_de_fecha_o_con_fecha_invalida_se_rechazan_con_mensaje_legible(): void
    {
        $this->actingAsAdmin();
        $this->importar('plan_cuentas', 'codigo,nombre,naturaleza,tipo', ['1105,Caja,debito,activo']);

        $sinFecha = $this->importar('saldos_iniciales', 'codigo_cuenta,debito,credito', ['1105,1000,0']);
        $fechaInvalida = $this->importar('saldos_iniciales', 'codigo_cuenta,fecha_corte,debito,credito', ['1105,31/12/2026,1000,0']);

        foreach ([$sinFecha, $fechaInvalida] as $migracion) {
            $this->assertSame(0, $migracion['registros_aceptados']);
            $this->assertSame(1, $migracion['registros_rechazados']);
            $this->assertStringNotContainsString('Undefined array key', implode(' ', $migracion['detalle_errores']));
            $this->assertStringContainsString('fecha de corte', implode(' ', $migracion['detalle_errores']));
        }
        $this->assertDatabaseCount('saldos_apertura', 0);
    }

    public function test_importa_saldos_validos(): void
    {
        $this->actingAsAdmin();
        $this->importar('plan_cuentas', 'codigo,nombre,naturaleza,tipo', ['1105,Caja,debito,activo']);

        $migracion = $this->importar('saldos_iniciales', 'codigo_cuenta,fecha_corte,debito,credito', ['1105,2026-12-31,1000,0']);

        $this->assertSame(1, $migracion['registros_aceptados']);
        $this->assertDatabaseCount('saldos_apertura', 1);
    }

    public function test_el_codigo_de_cuenta_puede_repetirse_en_otra_empresa(): void
    {
        $this->actingAsAdmin();
        $this->importar('plan_cuentas', 'codigo,nombre,naturaleza,tipo', ['1105,Caja,debito,activo']);

        $this->actingAsAdmin();
        $migracion = $this->importar('plan_cuentas', 'codigo,nombre,naturaleza,tipo', ['1105,Caja,debito,activo']);

        $this->assertSame(1, $migracion['registros_aceptados']);
        $this->assertDatabaseCount('plan_cuentas', 2);
    }
}
