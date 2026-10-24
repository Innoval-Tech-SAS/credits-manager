<?php

namespace Tests\Support;

use App\Redis\Contracts\RedisClienteInterface;
use App\Redis\Exceptions\RedisClienteException;

/** Redis Streams en memoria para tests: mismas garantías (grupo, pendientes, reclamo) sin red. */
class RedisClienteFalso implements RedisClienteInterface
{
    /** @var array<string, array<string, array<string, string>>> stream => id => campos */
    public array $streams = [];

    /** @var array<string, array<string, array{consumidor: string, entregado_en: float}>> "stream|grupo" => id => datos */
    public array $pendientes = [];

    /** @var array<string, array<string, true>> "stream|grupo" => ids ya entregados al grupo */
    private array $entregadas = [];

    private int $contador = 0;

    public bool $caido = false;

    public function agregar(string $stream, array $campos): string
    {
        $this->fallarSiCaido();

        $id = (++$this->contador).'-0';
        $this->streams[$stream][$id] = $campos;

        return $id;
    }

    public function crearGrupo(string $stream, string $grupo): void
    {
        $this->fallarSiCaido();

        $this->streams[$stream] ??= [];
        $this->pendientes[$this->clave($stream, $grupo)] ??= [];
    }

    public function leerNuevas(string $stream, string $grupo, string $consumidor, int $cantidad, int $bloqueoMs): array
    {
        $this->fallarSiCaido();

        $clave = $this->clave($stream, $grupo);
        $entregadas = $this->entregadas[$clave] ?? [];
        $resultado = [];

        foreach ($this->streams[$stream] ?? [] as $id => $campos) {
            if (isset($entregadas[$id])) {
                continue;
            }

            $this->entregadas[$clave][$id] = true;
            $this->pendientes[$clave][$id] = ['consumidor' => $consumidor, 'entregado_en' => microtime(true)];
            $resultado[$id] = $campos;

            if (count($resultado) >= $cantidad) {
                break;
            }
        }

        return $resultado;
    }

    public function reclamarInactivas(string $stream, string $grupo, string $consumidor, int $inactivoMs, int $cantidad): array
    {
        $this->fallarSiCaido();

        $clave = $this->clave($stream, $grupo);
        $resultado = [];

        foreach ($this->pendientes[$clave] ?? [] as $id => $datos) {
            if ((microtime(true) - $datos['entregado_en']) * 1000 < $inactivoMs || ! isset($this->streams[$stream][$id])) {
                continue;
            }

            $this->pendientes[$clave][$id] = ['consumidor' => $consumidor, 'entregado_en' => microtime(true)];
            $resultado[$id] = $this->streams[$stream][$id];

            if (count($resultado) >= $cantidad) {
                break;
            }
        }

        return $resultado;
    }

    public function confirmar(string $stream, string $grupo, array $ids): void
    {
        $this->fallarSiCaido();

        foreach ($ids as $id) {
            unset($this->pendientes[$this->clave($stream, $grupo)][$id], $this->streams[$stream][$id]);
        }
    }

    public function longitud(string $stream): int
    {
        $this->fallarSiCaido();

        return count($this->streams[$stream] ?? []);
    }

    public function pendientes(string $stream, string $grupo): int
    {
        $this->fallarSiCaido();

        return count($this->pendientes[$this->clave($stream, $grupo)] ?? []);
    }

    /** Simula que los pendientes llevan mucho tiempo sin confirmarse (un consumidor murió). */
    public function envejecerPendientes(): void
    {
        foreach ($this->pendientes as $clave => $ids) {
            foreach ($ids as $id => $datos) {
                $this->pendientes[$clave][$id]['entregado_en'] = 0.0;
            }
        }
    }

    /** @return list<array<string, mixed>> payloads decodificados de todas las entradas de un stream */
    public function payloads(string $stream): array
    {
        return array_values(array_map(
            fn (array $campos) => json_decode($campos['payload'], true),
            $this->streams[$stream] ?? [],
        ));
    }

    private function clave(string $stream, string $grupo): string
    {
        return $stream.'|'.$grupo;
    }

    private function fallarSiCaido(): void
    {
        if ($this->caido) {
            throw new RedisClienteException('Redis caído (simulado).');
        }
    }
}
