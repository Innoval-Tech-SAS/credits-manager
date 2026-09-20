<?php

namespace Tests\Concerns;

use App\Auditoria\Constants\AuditoriaConstants;

/** Requiere `$this->redis` (TestCase): lee lo que los servicios publicaron en el stream de auditoría. */
trait AfirmaAuditoria
{
    /** @return list<array<string, mixed>> */
    protected function eventosDeAuditoria(?string $accion = null, ?string $modulo = null): array
    {
        return array_values(array_filter(
            $this->redis->payloads(AuditoriaConstants::STREAM),
            fn (array $evento) => ($accion === null || $evento['accion'] === $accion) && ($modulo === null || $evento['modulo'] === $modulo),
        ));
    }

    /** Exige exactamente un evento de esa acción sobre esa entidad y lo devuelve para seguir afirmando sobre él. */
    protected function assertEventoAuditoria(string $accion, string $modulo, string $entidad, ?int $entidadId = null): array
    {
        $coincidentes = array_values(array_filter(
            $this->eventosDeAuditoria($accion, $modulo),
            fn (array $evento) => $evento['entidad'] === $entidad && ($entidadId === null || $evento['entidad_id'] === $entidadId),
        ));

        $this->assertCount(1, $coincidentes, "Se esperaba un evento {$accion} de {$modulo} sobre {$entidad}".($entidadId ? " #{$entidadId}" : '').'.');

        return $coincidentes[0];
    }

    protected function assertSinEventosDeAuditoria(?string $accion = null, ?string $modulo = null): void
    {
        $this->assertSame([], $this->eventosDeAuditoria($accion, $modulo));
    }
}
