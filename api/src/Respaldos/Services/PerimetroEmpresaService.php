<?php

namespace App\Respaldos\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Decide qué filas de cada tabla pertenecen a una empresa: directamente por `empresa_id`, o por una
 * cadena de claves foráneas hasta una tabla que sí lo tenga. Las tablas sin ningún camino no tienen
 * perímetro; el volcado de empresa las trata como globales solo si están en `respaldos.tablas_globales`.
 */
class PerimetroEmpresaService
{
    /** La propia empresa: su fila se identifica por `id`, no por `empresa_id`. */
    private const TABLA_EMPRESAS = 'empresas';

    /** @var array<string, bool> */
    private array $memoria = [];

    /** @var array<string, array<int, array{columns: array<int, string>, foreign_table: string, foreign_columns: array<int, string>}>> */
    private array $claves = [];

    public function tienePerimetro(string $tabla, array $visitando = []): bool
    {
        if ($tabla === self::TABLA_EMPRESAS) {
            return true;
        }

        if (array_key_exists($tabla, $this->memoria)) {
            return $this->memoria[$tabla];
        }

        if (in_array($tabla, $visitando, true)) {
            return false;
        }

        if (Schema::hasColumn($tabla, 'empresa_id')) {
            return $this->memoria[$tabla] = true;
        }

        foreach ($this->clavesDe($tabla) as $clave) {
            if ($clave['foreign_table'] !== $tabla && $this->tienePerimetro($clave['foreign_table'], [...$visitando, $tabla])) {
                return true;
            }
        }

        // Un resultado negativo depende del camino de visita: no se guarda para no contaminar otras tablas.
        return $visitando === [] ? $this->memoria[$tabla] = false : false;
    }

    /** Restringe la consulta de `$tabla` a las filas de `$empresaId`. Requiere `tienePerimetro($tabla)`. */
    public function restringir(Builder $consulta, string $tabla, int $empresaId, array $visitando = []): void
    {
        if ($tabla === self::TABLA_EMPRESAS) {
            $consulta->where("{$tabla}.id", $empresaId);

            return;
        }

        if (Schema::hasColumn($tabla, 'empresa_id')) {
            $consulta->where("{$tabla}.empresa_id", $empresaId);

            return;
        }

        foreach ($this->clavesDe($tabla) as $clave) {
            $padre = $clave['foreign_table'];

            if ($padre === $tabla || in_array($padre, $visitando, true) || ! $this->tienePerimetro($padre, [...$visitando, $tabla])) {
                continue;
            }

            $consulta->whereExists(function (Builder $subconsulta) use ($tabla, $clave, $padre, $empresaId, $visitando): void {
                $subconsulta->from($padre);

                foreach ($clave['columns'] as $i => $columna) {
                    $subconsulta->whereColumn("{$padre}.{$clave['foreign_columns'][$i]}", "{$tabla}.{$columna}");
                }

                $this->restringir($subconsulta, $padre, $empresaId, [...$visitando, $tabla]);
            });

            return;
        }

        throw new \LogicException("La tabla {$tabla} no tiene perímetro de empresa.");
    }

    /** @return array<int, array{columns: array<int, string>, foreign_table: string, foreign_columns: array<int, string>}> */
    private function clavesDe(string $tabla): array
    {
        return $this->claves[$tabla] ??= array_map(
            fn (array $clave) => [
                'columns' => $clave['columns'],
                'foreign_table' => $clave['foreign_table'],
                'foreign_columns' => $clave['foreign_columns'],
            ],
            Schema::getForeignKeys($tabla),
        );
    }
}
