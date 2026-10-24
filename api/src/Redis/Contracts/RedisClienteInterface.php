<?php

namespace App\Redis\Contracts;

/**
 * Operaciones de Redis Streams que necesita el proyecto. Los tests reemplazan la
 * implementación real por un fake en memoria (tests/Support/RedisClienteFalso).
 */
interface RedisClienteInterface
{
    /** XADD: agrega una entrada al stream y devuelve su id. */
    public function agregar(string $stream, array $campos): string;

    /** XGROUP CREATE ... 0 MKSTREAM: idempotente (ignora BUSYGROUP). */
    public function crearGrupo(string $stream, string $grupo): void;

    /**
     * XREADGROUP ... > : entradas nuevas del grupo. Bloquea hasta $bloqueoMs y devuelve
     * en cuanto llega una entrada (no hace polling).
     *
     * @return array<string, array<string, string>> id => campos
     */
    public function leerNuevas(string $stream, string $grupo, string $consumidor, int $cantidad, int $bloqueoMs): array;

    /**
     * XAUTOCLAIM: toma para $consumidor las entradas pendientes de otros consumidores
     * (p. ej. uno que murió antes de confirmar) inactivas por más de $inactivoMs.
     *
     * @return array<string, array<string, string>> id => campos
     */
    public function reclamarInactivas(string $stream, string $grupo, string $consumidor, int $inactivoMs, int $cantidad): array;

    /** XACK + XDEL: el stream solo conserva lo no procesado. */
    public function confirmar(string $stream, string $grupo, array $ids): void;

    /** XLEN. */
    public function longitud(string $stream): int;

    /** Entradas entregadas y aún sin confirmar del grupo. */
    public function pendientes(string $stream, string $grupo): int;
}
