<?php

namespace Tests\Unit\Redis;

use App\Redis\Services\RedisCliente;
use Illuminate\Support\Facades\Redis;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;
use Throwable;

/**
 * Contra el Redis real del compose. Se omite solo con `--exclude-group redis` (o si no hay Redis).
 * Usa streams con nombre único para no chocar con el listener que corre en el compose.
 */
#[Group('redis')]
class RedisClienteIntegracionTest extends TestCase
{
    private string $stream;

    private string $grupo = 'grupo-prueba';

    private RedisCliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stream = 'prueba:stream:'.uniqid();
        $this->cliente = new RedisCliente;

        try {
            $this->cliente->longitud($this->stream);
        } catch (Throwable $excepcion) {
            $this->markTestSkipped('Redis no disponible: '.$excepcion->getMessage());
        }

        $this->cliente->crearGrupo($this->stream, $this->grupo);
    }

    protected function tearDown(): void
    {
        try {
            Redis::connection('auditoria')->client()->del($this->stream);
        } catch (Throwable) {
        }

        parent::tearDown();
    }

    public function test_los_nombres_del_stream_son_literales_sin_prefijo(): void
    {
        $this->cliente->agregar($this->stream, ['payload' => 'x']);

        $this->assertSame(1, Redis::connection('auditoria')->client()->exists($this->stream));
    }

    public function test_agregar_y_leer_entrega_la_entrada_y_queda_pendiente_hasta_confirmar(): void
    {
        $id = $this->cliente->agregar($this->stream, ['evento_id' => 'e1', 'payload' => '{"a":1}']);

        $entradas = $this->cliente->leerNuevas($this->stream, $this->grupo, 'c1', 10, 100);

        $this->assertSame([$id => ['evento_id' => 'e1', 'payload' => '{"a":1}']], $entradas);
        $this->assertSame(1, $this->cliente->longitud($this->stream));
        $this->assertSame(1, $this->cliente->pendientes($this->stream, $this->grupo));

        $this->cliente->confirmar($this->stream, $this->grupo, [$id]);

        $this->assertSame(0, $this->cliente->longitud($this->stream), 'XACK + XDEL: el stream solo conserva lo no procesado.');
        $this->assertSame(0, $this->cliente->pendientes($this->stream, $this->grupo));
    }

    public function test_un_evento_emitido_antes_de_que_exista_el_listener_no_se_pierde(): void
    {
        $stream = 'prueba:sin-grupo:'.uniqid();
        $this->cliente->agregar($stream, ['payload' => 'llegó antes que el listener']);

        // El grupo se crea después, desde el inicio del stream ("0"): el evento sigue ahí.
        $this->cliente->crearGrupo($stream, $this->grupo);
        $entradas = $this->cliente->leerNuevas($stream, $this->grupo, 'c1', 10, 100);

        Redis::connection('auditoria')->client()->del($stream);

        $this->assertCount(1, $entradas);
    }

    public function test_crear_el_grupo_dos_veces_no_falla(): void
    {
        $this->cliente->crearGrupo($this->stream, $this->grupo);
        $this->cliente->crearGrupo($this->stream, $this->grupo);

        $this->addToAssertionCount(1);
    }

    public function test_sin_entradas_devuelve_vacio_al_vencer_el_bloqueo(): void
    {
        $inicio = microtime(true);

        $this->assertSame([], $this->cliente->leerNuevas($this->stream, $this->grupo, 'c1', 10, 300));
        $this->assertGreaterThanOrEqual(0.25, microtime(true) - $inicio);
    }

    public function test_la_entrega_es_inmediata_no_espera_al_vencimiento_del_bloqueo(): void
    {
        $host = escapeshellarg((string) config('database.redis.auditoria.host'));
        $puerto = (int) config('database.redis.auditoria.port');
        $db = (int) config('database.redis.auditoria.database');
        $stream = escapeshellarg($this->stream);

        // Otro proceso publica a los 400 ms mientras este consumidor está bloqueado hasta 5 s.
        $proceso = proc_open(
            [PHP_BINARY, '-r', "usleep(400000); \$r = new Redis; \$r->connect({$host}, {$puerto}); \$r->select({$db}); \$r->xAdd({$stream}, '*', ['payload' => 'push']);"],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $tuberias,
        );

        $inicio = microtime(true);
        $entradas = $this->cliente->leerNuevas($this->stream, $this->grupo, 'c1', 10, 5000);
        $espera = microtime(true) - $inicio;

        proc_close($proceso);

        $this->assertCount(1, $entradas);
        $this->assertGreaterThan(0.3, $espera);
        $this->assertLessThan(1.5, $espera, 'El listener debe despertar en cuanto llega el XADD, sin esperar al timeout de 5 s.');
    }

    public function test_dos_consumidores_del_mismo_grupo_no_reciben_la_misma_entrada(): void
    {
        $this->cliente->agregar($this->stream, ['payload' => '1']);
        $this->cliente->agregar($this->stream, ['payload' => '2']);

        $delPrimero = $this->cliente->leerNuevas($this->stream, $this->grupo, 'c1', 1, 100);
        $delSegundo = $this->cliente->leerNuevas($this->stream, $this->grupo, 'c2', 1, 100);

        $this->assertCount(1, $delPrimero);
        $this->assertCount(1, $delSegundo);
        $this->assertSame([], array_intersect_key($delPrimero, $delSegundo));
    }

    public function test_lo_que_dejo_pendiente_un_consumidor_muerto_se_reclama(): void
    {
        $id = $this->cliente->agregar($this->stream, ['payload' => 'huérfano']);
        $this->cliente->leerNuevas($this->stream, $this->grupo, 'muerto', 10, 100);

        usleep(150_000);

        $this->assertSame([], $this->cliente->reclamarInactivas($this->stream, $this->grupo, 'vivo', 60_000, 10), 'Algo reciente no se le quita a otro consumidor.');

        $reclamadas = $this->cliente->reclamarInactivas($this->stream, $this->grupo, 'vivo', 100, 10);

        $this->assertSame([$id => ['payload' => 'huérfano']], $reclamadas);

        $this->cliente->confirmar($this->stream, $this->grupo, [$id]);
        $this->assertSame(0, $this->cliente->pendientes($this->stream, $this->grupo));
    }

    public function test_confirmar_sin_ids_no_hace_nada(): void
    {
        $this->cliente->confirmar($this->stream, $this->grupo, []);

        $this->addToAssertionCount(1);
    }
}
