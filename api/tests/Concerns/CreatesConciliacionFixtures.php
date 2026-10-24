<?php

namespace Tests\Concerns;

use App\Conciliacion\Enums\Banco;
use App\Conciliacion\Enums\EstadoExtracto;
use App\Conciliacion\Enums\EstadoMovimientoExtracto;
use App\Conciliacion\Enums\TipoCuentaBancaria;
use App\Conciliacion\Models\CuentaBancaria;
use App\Conciliacion\Models\ExtractoBancario;
use App\Conciliacion\Models\MovimientoExtracto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
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

    /** Extracto ya cargado (sin pasar por el job asíncrono): para tests de matching que no necesitan probar la carga. */
    protected function crearExtractoBancario(CuentaBancaria $cuenta, array $overrides = []): ExtractoBancario
    {
        return ExtractoBancario::create(array_merge([
            'cuenta_bancaria_id' => $cuenta->id,
            'fecha_desde' => '2026-10-01',
            'fecha_hasta' => '2026-10-10',
            'saldo_anterior' => 0,
            'total_abonos' => 0,
            'total_cargos' => 0,
            'saldo_actual' => 0,
            'archivo_ruta' => 'conciliacion/extracto-de-prueba.xlsx',
            'estado' => EstadoExtracto::Cargado,
            'cargado_por' => Auth::id(),
        ], $overrides));
    }

    protected function crearMovimientoExtracto(ExtractoBancario $extracto, array $overrides = []): MovimientoExtracto
    {
        static $orden = 0;
        $orden++;

        return MovimientoExtracto::create(array_merge([
            'extracto_bancario_id' => $extracto->id,
            'fecha' => '2026-10-05',
            'descripcion' => 'CONSIGNACION',
            'orden' => $orden,
            'valor' => 1000,
            'saldo' => 1000,
            'estado' => EstadoMovimientoExtracto::Pendiente,
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
