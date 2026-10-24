<?php

namespace App\Respaldos\Services;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Escribe los datos como INSERT en streaming. Sin empresa: toda la instancia. Con empresa: solo las filas
 * de esa empresa, y las tablas sin perímetro únicamente si están en `respaldos.tablas_globales`.
 * Las columnas de `respaldos.columnas_omitidas` (hashes de contraseña, tokens) salen vacías.
 *
 * @return list<string> tablas omitidas por no tener perímetro de empresa
 */
class VolcadoDatosService
{
    private const FILAS_POR_INSERT = 500;

    public function __construct(private PerimetroEmpresaService $perimetro) {}

    /** @param Closure(string): void $escribir */
    public function escribir(Closure $escribir, ?int $empresaId = null): array
    {
        $omitidas = [];
        $excluidas = config('respaldos.tablas_excluidas');
        $globales = config('respaldos.tablas_globales');
        $omitidasPorColumna = config('respaldos.columnas_omitidas');

        foreach (array_column(Schema::getTables(), 'name') as $tabla) {
            if (in_array($tabla, $excluidas, true)) {
                continue;
            }

            if ($empresaId !== null && ! $this->perimetro->tienePerimetro($tabla) && ! in_array($tabla, $globales, true)) {
                $omitidas[] = $tabla;

                continue;
            }

            $columnas = Schema::getColumnListing($tabla);
            $consulta = DB::table($tabla);

            if ($empresaId !== null && $this->perimetro->tienePerimetro($tabla)) {
                $this->perimetro->restringir($consulta, $tabla, $empresaId);
            }

            $this->escribirTabla($escribir, $tabla, $columnas, $omitidasPorColumna, $consulta);
        }

        return $omitidas;
    }

    /** @param list<string> $columnas @param list<string> $omitidasPorColumna */
    private function escribirTabla(Closure $escribir, string $tabla, array $columnas, array $omitidasPorColumna, $consulta): void
    {
        $lista = implode(', ', array_map(fn (string $c) => '`'.$c.'`', $columnas));
        $escribir("\n-- Datos de {$tabla}\n");

        $lote = [];
        foreach ($consulta->cursor() as $fila) {
            $valores = [];
            foreach ($columnas as $columna) {
                $valores[] = in_array($columna, $omitidasPorColumna, true) ? "''" : $this->valor($fila->{$columna});
            }

            $lote[] = '('.implode(',', $valores).')';

            if (count($lote) === self::FILAS_POR_INSERT) {
                $escribir("INSERT INTO `{$tabla}` ({$lista}) VALUES\n".implode(",\n", $lote).";\n");
                $lote = [];
            }
        }

        if ($lote !== []) {
            $escribir("INSERT INTO `{$tabla}` ({$lista}) VALUES\n".implode(",\n", $lote).";\n");
        }
    }

    private function valor(mixed $valor): string
    {
        return match (true) {
            $valor === null => 'NULL',
            is_bool($valor) => $valor ? '1' : '0',
            is_int($valor), is_float($valor) => (string) $valor,
            default => DB::getPdo()->quote((string) $valor),
        };
    }
}
