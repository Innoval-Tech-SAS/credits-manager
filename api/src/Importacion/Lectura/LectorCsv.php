<?php

namespace App\Importacion\Lectura;

use App\Importacion\Definiciones\DefinicionImportacion;
use App\Importacion\Enums\TipoImportacion;
use Generator;
use RuntimeException;

/**
 * Lee un CSV en streaming (fgetcsv, memoria constante). Detecta el separador (`;` o `,`), acepta UTF-8 con o sin BOM
 * y Windows-1252 (convierte cada campo que no sea UTF-8 válido) y localiza el encabezado por nombre exacto dentro de
 * las primeras filas, porque la hoja trae título y descripción antes de los encabezados.
 */
final class LectorCsv
{
    private const FILAS_BUSCANDO_ENCABEZADO = 10;

    /** @var resource|null */
    private $handle = null;

    private string $separador = ',';

    /** @var array<string, int> encabezado exacto → índice de columna */
    private array $indices = [];

    private int $registro = 0;

    public function __construct(private readonly string $ruta, private readonly TipoImportacion $tipo) {}

    /**
     * Comprueba el archivo sin procesarlo: devuelve los encabezados obligatorios que faltan, o null si está completo.
     *
     * @return list<string>|null
     */
    public function encabezadosFaltantes(): ?array
    {
        $this->abrir();

        try {
            $this->localizarEncabezado();

            return null;
        } catch (FormatoCsvInvalido $excepcion) {
            return $excepcion->faltantes;
        } finally {
            $this->cerrar();
        }
    }

    /**
     * Recorre las filas de datos. Cada elemento trae el número de registro del archivo (fila reportada al usuario) y los
     * valores crudos indexados por la clave interna de la columna. Las filas completamente vacías se omiten.
     *
     * @return Generator<int, array{fila: int, valores: array<string, string>}>
     */
    public function filas(): Generator
    {
        $this->abrir();

        try {
            $this->localizarEncabezado();
            $definicion = DefinicionImportacion::para($this->tipo)['columnas'];

            while (($registro = $this->siguienteRegistro()) !== null) {
                if (array_filter($registro, fn (string $campo) => $campo !== '') === []) {
                    continue;
                }

                $valores = [];
                foreach ($definicion as $columna) {
                    $indice = $this->indices[$columna['encabezado']] ?? null;
                    $valores[$columna['clave']] = $indice === null ? '' : trim($registro[$indice] ?? '');
                }

                yield ['fila' => $this->registro, 'valores' => $valores];
            }
        } finally {
            $this->cerrar();
        }
    }

    private function abrir(): void
    {
        $this->handle = is_file($this->ruta) ? fopen($this->ruta, 'rb') : false;

        if ($this->handle === false) {
            throw new RuntimeException('No se pudo abrir el archivo de importación.');
        }

        $muestra = '';
        for ($linea = 0; $linea < self::FILAS_BUSCANDO_ENCABEZADO && ($fila = fgets($this->handle)) !== false; $linea++) {
            $muestra .= $fila;
        }
        rewind($this->handle);

        $this->separador = substr_count($muestra, ',') > substr_count($muestra, ';') ? ',' : ';';

        $bom = fread($this->handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($this->handle);
        }

        $this->registro = 0;
        $this->indices = [];
    }

    private function cerrar(): void
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }

        $this->handle = null;
    }

    /** Busca la fila de encabezados entre las primeras del archivo y deja el mapa nombre → columna. */
    private function localizarEncabezado(): void
    {
        $obligatorios = DefinicionImportacion::encabezadosObligatorios($this->tipo);
        $mejorFaltantes = $obligatorios;

        for ($intento = 0; $intento < self::FILAS_BUSCANDO_ENCABEZADO; $intento++) {
            $campos = $this->siguienteRegistro();

            if ($campos === null) {
                break;
            }

            $mapa = [];
            foreach ($campos as $indice => $encabezado) {
                $mapa[trim($encabezado)] ??= $indice;
            }

            $faltantes = array_values(array_filter($obligatorios, fn (string $nombre) => ! array_key_exists($nombre, $mapa)));

            if ($faltantes === []) {
                $this->indices = $mapa;

                return;
            }

            if (count($faltantes) < count($mejorFaltantes)) {
                $mejorFaltantes = $faltantes;
            }
        }

        throw new FormatoCsvInvalido($mejorFaltantes);
    }

    /** @return list<string>|null campos del registro ya convertidos a UTF-8, o null al final del archivo */
    private function siguienteRegistro(): ?array
    {
        $campos = fgetcsv($this->handle, null, $this->separador, '"', '\\');

        if ($campos === false) {
            return null;
        }

        $this->registro++;

        return array_map(
            fn (?string $campo) => $this->aUtf8($campo ?? ''),
            $campos,
        );
    }

    private function aUtf8(string $campo): string
    {
        return mb_check_encoding($campo, 'UTF-8') ? $campo : mb_convert_encoding($campo, 'UTF-8', 'Windows-1252');
    }
}
