<?php

namespace Tests\Concerns;

use App\Conciliacion\Enums\Banco;
use App\Conciliacion\Enums\TipoCuentaBancaria;
use App\Conciliacion\Models\CuentaBancaria;
use Illuminate\Http\UploadedFile;
use OpenSpout\Common\Entity\Cell\EmptyCell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/** Fixtures del módulo de Conciliación bancaria. */
trait CreatesConciliacionFixtures
{
    use CreatesContabilidadFixtures;

    protected function crearCuentaBancaria(array $overrides = []): CuentaBancaria
    {
        static $contador = 0;
        $contador++;

        return CuentaBancaria::create(array_merge([
            'banco' => Banco::Bancolombia,
            'numero_cuenta' => "1234567890{$contador}",
            'tipo_cuenta' => TipoCuentaBancaria::Ahorros,
            'plan_cuenta_id' => $this->crearCuenta()->id,
        ], $overrides));
    }

    /**
     * Genera un xlsx real con el formato del extracto de Bancolombia (resumen de 4 filas + encabezado +
     * movimientos), para subirlo en tests de carga. `$movimientos` es una lista de
     * `['fecha' => 'd/mm', 'descripcion' => string, 'valor' => float, 'saldo' => float]`.
     */
    protected function archivoExtracto(string $numeroCuenta, array $resumen, array $movimientos): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'extracto').'.xlsx';

        $escritor = new Writer;
        $escritor->openToFile($ruta);

        $escritor->addRow(new Row([
            new StringCell('SALDO ANTERIOR'), new EmptyCell(null), new NumericCell($resumen['saldo_anterior']),
            new StringCell('SALDO PROMEDIO'), new EmptyCell(null), new NumericCell(0),
        ]));
        $escritor->addRow(new Row([
            new StringCell('TOTAL ABONOS'), new EmptyCell(null), new NumericCell($resumen['total_abonos']),
            new StringCell('CUENTAS X COBRAR'), new EmptyCell(null), new NumericCell(0),
        ]));
        $escritor->addRow(new Row([
            new StringCell('TOTAL CARGOS'), new EmptyCell(null), new NumericCell($resumen['total_cargos']),
            new StringCell('VALOR INTERESES PAGADOS'), new EmptyCell(null), new NumericCell(0),
        ]));
        $escritor->addRow(new Row([
            new StringCell('SALDO ACTUAL'), new EmptyCell(null), new NumericCell($resumen['saldo_actual']),
            new StringCell('RETEFUENTE'), new EmptyCell(null), new NumericCell(0),
        ]));
        $escritor->addRow(new Row([
            new StringCell('FECHA'), new StringCell('DESCRIPCIÓN'), new StringCell('SUCURSAL'),
            new StringCell('DCTO.'), new StringCell('VALOR'), new StringCell('SALDO'),
        ]));

        foreach ($movimientos as $movimiento) {
            $escritor->addRow(new Row([
                new StringCell($movimiento['fecha']), new StringCell($movimiento['descripcion']),
                new EmptyCell(null), new EmptyCell(null),
                new NumericCell($movimiento['valor']), new NumericCell($movimiento['saldo']),
            ]));
        }

        $escritor->close();

        return new UploadedFile($ruta, "{$numeroCuenta}_000000_0.xlsx", 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', test: true);
    }
}
