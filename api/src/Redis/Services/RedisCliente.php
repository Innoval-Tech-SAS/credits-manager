<?php

namespace App\Redis\Services;

use App\Redis\Contracts\RedisClienteInterface;
use App\Redis\Exceptions\RedisClienteException;
use Illuminate\Support\Facades\Redis as RedisManager;
use Redis;
use RedisException;

/**
 * Cliente sobre phpredis para la conexión `auditoria` (sin prefijo). phpredis devuelve `false`
 * ante un error de respuesta en lugar de lanzar: `ejecutar()` lo convierte en excepción y,
 * ante un fallo de conexión, descarta la conexión para que la siguiente llamada reconecte.
 */
class RedisCliente implements RedisClienteInterface
{
    private const CONEXION = 'auditoria';

    public function agregar(string $stream, array $campos): string
    {
        $id = $this->ejecutar(fn (Redis $redis) => $redis->xAdd($stream, '*', $campos));

        if (! is_string($id)) {
            throw new RedisClienteException('XADD no devolvió un id de entrada.');
        }

        return $id;
    }

    public function crearGrupo(string $stream, string $grupo): void
    {
        try {
            $this->ejecutar(fn (Redis $redis) => $redis->xGroup('CREATE', $stream, $grupo, '0', true));
        } catch (RedisClienteException $excepcion) {
            if (! str_contains($excepcion->getMessage(), 'BUSYGROUP')) {
                throw $excepcion;
            }
        }
    }

    public function leerNuevas(string $stream, string $grupo, string $consumidor, int $cantidad, int $bloqueoMs): array
    {
        $respuesta = $this->ejecutar(
            fn (Redis $redis) => $redis->xReadGroup($grupo, $consumidor, [$stream => '>'], $cantidad, $bloqueoMs),
        );

        return is_array($respuesta) ? ($respuesta[$stream] ?? []) : [];
    }

    public function reclamarInactivas(string $stream, string $grupo, string $consumidor, int $inactivoMs, int $cantidad): array
    {
        $respuesta = $this->ejecutar(
            fn (Redis $redis) => $redis->xAutoClaim($stream, $grupo, $consumidor, $inactivoMs, '0-0', $cantidad),
        );

        return is_array($respuesta) && isset($respuesta[1]) && is_array($respuesta[1]) ? $respuesta[1] : [];
    }

    public function confirmar(string $stream, string $grupo, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $this->ejecutar(function (Redis $redis) use ($stream, $grupo, $ids) {
            $redis->xAck($stream, $grupo, $ids);

            return $redis->xDel($stream, $ids);
        });
    }

    public function longitud(string $stream): int
    {
        return (int) $this->ejecutar(fn (Redis $redis) => $redis->xLen($stream));
    }

    public function pendientes(string $stream, string $grupo): int
    {
        $resumen = $this->ejecutar(fn (Redis $redis) => $redis->xPending($stream, $grupo));

        return is_array($resumen) ? (int) ($resumen[0] ?? 0) : 0;
    }

    private function ejecutar(callable $operacion): mixed
    {
        try {
            $cliente = RedisManager::connection(self::CONEXION)->client();
            $cliente->clearLastError();

            $resultado = $operacion($cliente);
        } catch (RedisException $excepcion) {
            RedisManager::purge(self::CONEXION);

            throw new RedisClienteException($excepcion->getMessage(), previous: $excepcion);
        }

        $error = $cliente->getLastError();

        if ($error !== null) {
            $cliente->clearLastError();

            throw new RedisClienteException($error);
        }

        return $resultado;
    }
}
