<?php

namespace App\Conciliacion\Lectura;

use App\Conciliacion\Lectura\Data\ExtractoLeido;
use App\Conciliacion\Lectura\Data\MovimientoLeido;
use App\Conciliacion\Lectura\Data\ResumenExtracto;
use App\Core\Dinero\Dinero;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Lee el extracto de Bancolombia: filas 1-4 un resumen en dos bloques (columnas A/C y D/F, solo se usa el
 * primero), fila 5 el encabezado (`FECHA|DESCRIPCIÓN|SUCURSAL|DCTO.|VALOR|SALDO`) y de ahí en adelante los
 * movimientos. La fecha de cada fila viene como texto `d/m` sin año, y el archivo puede traer varios meses
 * (uno real, de prueba, trajo julio a septiembre completos): el año se reconstruye con un puntero que
 * arranca en `$anioPrimeraFila`/`$mesPrimeraFila` (el mes de la primera fila, el único dato externo que
 * hace falta) y avanza un mes exacto cada vez que el mes de la fila cambia — las filas están en orden
 * cronológico, así que nunca salta más de un mes a la vez; si lo hace, el archivo está corrupto o el ancla
 * es incorrecta. Formato específico de este banco — otros bancos necesitarán su propio lector (ver el
 * docblock de `cuentas_bancarias` en la migración).
 */
final class LectorExtractoBancolombia
{
    private const FILAS_BUSCANDO_ENCABEZADO = 10;

    private const ETIQUETAS_RESUMEN = [
        'SALDO ANTERIOR' => 'saldoAnterior',
        'TOTAL ABONOS' => 'totalAbonos',
        'TOTAL CARGOS' => 'totalCargos',
        'SALDO ACTUAL' => 'saldoActual',
    ];

    private int $anioActual;

    private int $mesActual;

    public function __construct(
        private readonly string $ruta,
        private readonly int $anioPrimeraFila,
        private readonly int $mesPrimeraFila,
    ) {}

    /** Comprueba el archivo sin procesarlo por completo: true si encuentra la fila de encabezados del banco. */
    public function cabeceraValida(): bool
    {
        try {
            $this->leer();

            return true;
        } catch (FormatoExtractoInvalido) {
            return false;
        }
    }

    public function leer(): ExtractoLeido
    {
        $reader = new Reader;
        $reader->open($this->ruta);

        try {
            $sheet = $reader->getSheetIterator()->current();

            $this->anioActual = $this->anioPrimeraFila;
            $this->mesActual = $this->mesPrimeraFila;

            $valoresResumen = [];
            $movimientos = [];
            $encabezadoEncontrado = false;
            $fila = 0;
            $orden = 0;

            foreach ($sheet->getRowIterator() as $row) {
                $fila++;
                $celdas = $row->toArray();

                if (! $encabezadoEncontrado) {
                    $primeraCelda = $this->aTexto($celdas[0] ?? null);

                    if (strcasecmp(trim($primeraCelda), 'FECHA') === 0) {
                        $encabezadoEncontrado = true;

                        continue;
                    }

                    $etiqueta = strtoupper(trim($primeraCelda));

                    if (isset(self::ETIQUETAS_RESUMEN[$etiqueta])) {
                        $valoresResumen[self::ETIQUETAS_RESUMEN[$etiqueta]] = $this->aDinero($celdas[2] ?? null);
                    }

                    if ($fila > self::FILAS_BUSCANDO_ENCABEZADO) {
                        throw new FormatoExtractoInvalido;
                    }

                    continue;
                }

                if ($row->isEmpty()) {
                    continue;
                }

                $movimientos[] = new MovimientoLeido(
                    fecha: $this->reconstruirFecha($this->aTexto($celdas[0] ?? null)),
                    descripcion: trim($this->aTexto($celdas[1] ?? null)),
                    sucursal: $this->aTextoONulo($celdas[2] ?? null),
                    documento: $this->aTextoONulo($celdas[3] ?? null),
                    valor: $this->aDinero($celdas[4] ?? null),
                    saldo: $this->aDinero($celdas[5] ?? null),
                    orden: $orden++,
                );
            }

            if (! $encabezadoEncontrado || count($valoresResumen) !== count(self::ETIQUETAS_RESUMEN)) {
                throw new FormatoExtractoInvalido;
            }

            return new ExtractoLeido(
                resumen: new ResumenExtracto(...$valoresResumen),
                movimientos: $movimientos,
            );
        } finally {
            $reader->close();
        }
    }

    /** @throws FormatoExtractoInvalido si la fila salta más de un mes respecto de la anterior (archivo desordenado o ancla incorrecta) */
    private function reconstruirFecha(string $texto): string
    {
        if (! preg_match('/^(\d{1,2})\/(\d{1,2})$/', trim($texto), $coincidencia)) {
            throw new FormatoExtractoInvalido;
        }

        $dia = (int) $coincidencia[1];
        $mes = (int) $coincidencia[2];

        if ($mes !== $this->mesActual) {
            $siguienteMes = $this->mesActual === 12 ? 1 : $this->mesActual + 1;

            if ($mes !== $siguienteMes) {
                throw new FormatoExtractoInvalido;
            }

            if ($siguienteMes === 1) {
                $this->anioActual++;
            }

            $this->mesActual = $mes;
        }

        if (! checkdate($mes, $dia, $this->anioActual)) {
            throw new FormatoExtractoInvalido;
        }

        return sprintf('%04d-%02d-%02d', $this->anioActual, $mes, $dia);
    }

    private function aDinero(mixed $valor): Dinero
    {
        if ($valor === null || $valor === '') {
            throw new FormatoExtractoInvalido;
        }

        return Dinero::desdePesos(is_string($valor) ? trim($valor) : $valor);
    }

    private function aTexto(mixed $valor): string
    {
        return $valor === null ? '' : trim((string) $valor);
    }

    private function aTextoONulo(mixed $valor): ?string
    {
        $texto = $this->aTexto($valor);

        return $texto === '' ? null : $texto;
    }
}
