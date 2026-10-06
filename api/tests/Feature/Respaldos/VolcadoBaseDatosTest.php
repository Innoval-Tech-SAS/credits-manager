<?php

namespace Tests\Feature\Respaldos;

use App\Respaldos\Services\VolcadoBaseDatosService;
use Tests\TestCase;

class VolcadoBaseDatosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.connections.mysql.password' => 'clave-secreta-de-prueba',
            'database.connections.mysql.username' => 'usuario-app',
            'database.connections.mysql.database' => 'creditos_db',
        ]);
    }

    public function test_la_clave_viaja_en_el_entorno_y_nunca_en_los_argumentos(): void
    {
        [$argumentos, $entorno] = app(VolcadoBaseDatosService::class)->comando('/tmp/salida.sql');

        $this->assertSame(['MYSQL_PWD' => 'clave-secreta-de-prueba'], $entorno);

        foreach ($argumentos as $argumento) {
            $this->assertStringNotContainsString('clave-secreta-de-prueba', $argumento);
        }
    }

    public function test_el_volcado_es_consistente_y_omite_las_tablas_de_estado_tecnico(): void
    {
        [$argumentos] = app(VolcadoBaseDatosService::class)->comando('/tmp/salida.sql');

        $this->assertContains('--single-transaction', $argumentos);
        $this->assertContains('--routines', $argumentos);
        $this->assertContains('--triggers', $argumentos);
        $this->assertContains('--no-tablespaces', $argumentos);
        $this->assertContains('--result-file=/tmp/salida.sql', $argumentos);
        $this->assertContains('--ignore-table=creditos_db.sessions', $argumentos);
        $this->assertContains('--ignore-table=creditos_db.cache', $argumentos);
        $this->assertContains('--ignore-table=creditos_db.cache_locks', $argumentos);
        $this->assertSame('creditos_db', end($argumentos));
    }
}
