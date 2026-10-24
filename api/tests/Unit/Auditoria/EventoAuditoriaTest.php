<?php

namespace Tests\Unit\Auditoria;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Data\AutorAuditoria;
use App\Auditoria\Data\EventoAuditoria;
use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Exceptions\EventoAuditoriaInvalidoException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Tests\TestCase;

class EventoAuditoriaTest extends TestCase
{
    private function evento(array $sobrescribir = []): EventoAuditoria
    {
        return EventoAuditoria::crear(...array_merge([
            'accion' => AccionAuditoria::Crear,
            'modulo' => 'clientes',
            'descripcion' => 'Cliente creado',
            'autor' => new AutorAuditoria(5, 'Ana', 'ana', 'admin', false),
            'empresaId' => 3,
        ], $sobrescribir));
    }

    public function test_genera_un_uuid_y_la_fecha_del_momento_de_emitir(): void
    {
        $antes = CarbonImmutable::now()->subSecond();

        $evento = $this->evento();

        $this->assertTrue(Str::isUuid($evento->eventoId));
        $this->assertTrue($evento->fechaHora->greaterThan($antes));
    }

    public function test_es_ida_y_vuelta_estable_por_array_y_json(): void
    {
        $evento = $this->evento([
            'ip' => '10.0.0.1', 'entidad' => 'cliente', 'entidadId' => 9,
            'valoresAnteriores' => ['nombre' => 'A'], 'valoresNuevos' => ['nombre' => 'B'], 'motivo' => 'Corrección',
        ]);

        $copia = EventoAuditoria::desdeArray(json_decode($evento->aJson(), true));

        $this->assertEquals($evento->aArray(), $copia->aArray());
        $this->assertSame($evento->eventoId, $copia->eventoId);
        $this->assertSame(9, $copia->entidadId);
        $this->assertSame(3, $copia->empresaId);
        $this->assertSame(5, $copia->autor->usuarioId);
        $this->assertTrue($evento->fechaHora->equalTo($copia->fechaHora));
    }

    public function test_trunca_textos_a_la_longitud_de_su_columna(): void
    {
        $evento = $this->evento([
            'descripcion' => str_repeat('á', AuditoriaConstants::LONGITUD_DESCRIPCION + 50),
            'modulo' => str_repeat('m', 80),
        ]);

        $this->assertSame(AuditoriaConstants::LONGITUD_DESCRIPCION, mb_strlen($evento->descripcion));
        $this->assertStringEndsWith('…', $evento->descripcion);
        $this->assertSame(AuditoriaConstants::LONGITUD_MODULO, mb_strlen($evento->modulo));
    }

    public function test_sanea_utf8_invalido(): void
    {
        $evento = $this->evento(['descripcion' => "Cliente \xB1 inválido"]);

        $this->assertTrue(mb_check_encoding($evento->descripcion, 'UTF-8'));
        $this->assertNotFalse(json_encode($evento->aArray()));
    }

    public function test_nunca_guarda_campos_sensibles_a_ninguna_profundidad(): void
    {
        $evento = $this->evento([
            'valoresNuevos' => ['usuario' => 'ana', 'password_hash' => 'x', 'datos' => ['clave' => 'y', 'ok' => 1], 'Token' => 'z'],
        ]);

        $this->assertSame(['usuario' => 'ana', 'datos' => ['ok' => 1]], $evento->valoresNuevos);
    }

    public function test_valores_vacios_quedan_en_null(): void
    {
        $evento = $this->evento(['valoresAnteriores' => [], 'valoresNuevos' => null]);

        $this->assertNull($evento->valoresAnteriores);
        $this->assertNull($evento->valoresNuevos);
    }

    public function test_reemplaza_valores_gigantes_por_una_marca_de_truncado(): void
    {
        $evento = $this->evento(['valoresNuevos' => ['blob' => str_repeat('x', AuditoriaConstants::BYTES_MAXIMOS_VALORES + 10)]]);

        $this->assertTrue($evento->valoresNuevos['_truncado']);
    }

    public function test_rechaza_valores_que_no_se_pueden_serializar(): void
    {
        $this->expectException(EventoAuditoriaInvalidoException::class);

        $this->evento(['valoresNuevos' => ['recurso' => NAN]]);
    }

    public function test_rechaza_uuid_invalido_y_textos_obligatorios_vacios(): void
    {
        foreach ([['eventoId' => 'no-es-uuid'], ['modulo' => '  '], ['descripcion' => '']] as $invalido) {
            try {
                $this->evento($invalido);
                $this->fail('Debió lanzar EventoAuditoriaInvalidoException con '.json_encode($invalido));
            } catch (EventoAuditoriaInvalidoException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_desde_array_rechaza_accion_desconocida(): void
    {
        $datos = $this->evento()->aArray();
        $datos['accion'] = 'inventada';

        $this->expectException(EventoAuditoriaInvalidoException::class);

        EventoAuditoria::desdeArray($datos);
    }
}
