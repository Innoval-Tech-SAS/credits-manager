<?php

namespace App\Contabilidad\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Services\EmisorAuditoria;
use App\Contabilidad\Documentos\InformeContablePdf;
use App\Contabilidad\Enums\NaturalezaCuenta;
use App\Contabilidad\Repositories\PlanCuentaRepository;
use App\Core\Dinero\Dinero;
use App\Empresas\Services\EmpresaService;
use App\Impresion\Data\ColumnaDocumento;
use App\Impresion\Data\FechaDocumento;
use App\Impresion\Data\PdfGenerado;
use App\Impresion\Data\SeccionDocumento;
use App\Impresion\Data\TablaDocumento;
use App\Impresion\Data\TotalDocumento;
use App\Impresion\Enums\TipoColumnaDocumento;
use App\Impresion\Services\GeneradorPdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * PDF de los informes contables. Cada método arma sus datos con el servicio de cálculo correspondiente y deja la
 * descarga en la bitácora. Los cálculos no se repiten aquí: el PDF y el Excel salen de los mismos servicios.
 */
class InformeContablePdfService
{
    public function __construct(
        protected ReporteContableService $reportes,
        protected AuxiliarContableService $auxiliares,
        protected PlanCuentaRepository $cuentas,
        protected EmpresaService $empresas,
        protected GeneradorPdf $generador,
        protected EmisorAuditoria $auditoria,
    ) {}

    public function balancePrueba(string $hasta): PdfGenerado
    {
        $empresa = $this->empresas->obtenerPropia();
        $balance = $this->reportes->balancePrueba($empresa->id, $hasta);

        $documento = new InformeContablePdf(
            titulo: 'Balance de prueba',
            nombreArchivo: "balance-de-prueba-{$hasta}",
            alcance: 'Todas las cuentas de movimiento',
            fechasInforme: [new FechaDocumento('Corte', Carbon::parse($hasta))],
            tituloTabla: 'Saldos por cuenta',
            columnas: [
                new ColumnaDocumento('codigo', 'Código', TipoColumnaDocumento::Texto, 14),
                new ColumnaDocumento('nombre', 'Cuenta', TipoColumnaDocumento::Texto, 36),
                new ColumnaDocumento('debitos', 'Débitos', TipoColumnaDocumento::Monetario, 17),
                new ColumnaDocumento('creditos', 'Créditos', TipoColumnaDocumento::Monetario, 17),
                new ColumnaDocumento('saldo', 'Saldo', TipoColumnaDocumento::Monetario, 16),
            ],
            filas: $balance['saldos']->all(),
            totales: [
                new TotalDocumento('Total débitos', $balance['total_debito']),
                new TotalDocumento('Total créditos', $balance['total_credito']),
            ],
            secciones: [$this->cuadre('Débitos y créditos', $balance['total_debito'], $balance['total_credito'])],
        );

        return $this->generar($documento, $empresa, 'Balance de prueba', "hasta {$hasta}");
    }

    public function libroDiario(string $desde, string $hasta): PdfGenerado
    {
        $empresa = $this->empresas->obtenerPropia();
        $diario = $this->reportes->libroDiario($empresa->id, $desde, $hasta);

        $filas = $diario['movimientos']->map(fn ($movimiento) => [
            'fecha' => Carbon::parse($movimiento->fecha),
            'comprobante' => "{$movimiento->tipo}-{$movimiento->consecutivo}",
            'cuenta' => "{$movimiento->cuenta_codigo} {$movimiento->cuenta_nombre}",
            'descripcion' => $movimiento->descripcion,
            'tercero' => $movimiento->tercero,
            'debito' => $movimiento->debito,
            'credito' => $movimiento->credito,
        ])->all();

        $documento = new InformeContablePdf(
            titulo: 'Libro diario',
            nombreArchivo: "libro-diario-{$desde}-{$hasta}",
            alcance: 'Todos los comprobantes contabilizados',
            fechasInforme: [new FechaDocumento('Desde', Carbon::parse($desde)), new FechaDocumento('Hasta', Carbon::parse($hasta))],
            tituloTabla: 'Movimientos',
            columnas: [
                new ColumnaDocumento('fecha', 'Fecha', TipoColumnaDocumento::Fecha, 10),
                new ColumnaDocumento('comprobante', 'Comprobante', TipoColumnaDocumento::Texto, 12),
                new ColumnaDocumento('cuenta', 'Cuenta', TipoColumnaDocumento::Texto, 20),
                new ColumnaDocumento('descripcion', 'Detalle', TipoColumnaDocumento::Texto, 16),
                new ColumnaDocumento('tercero', 'Tercero', TipoColumnaDocumento::Texto, 14),
                new ColumnaDocumento('debito', 'Débito', TipoColumnaDocumento::Monetario, 14),
                new ColumnaDocumento('credito', 'Crédito', TipoColumnaDocumento::Monetario, 14),
            ],
            filas: $filas,
            totales: [
                new TotalDocumento('Total débitos', $diario['total_debito']),
                new TotalDocumento('Total créditos', $diario['total_credito']),
            ],
            secciones: [$this->cuadre('Partida doble', $diario['total_debito'], $diario['total_credito'])],
        );

        return $this->generar($documento, $empresa, 'Libro diario', "del {$desde} al {$hasta}");
    }

    public function libroMayor(int $cuentaId, string $desde, string $hasta): PdfGenerado
    {
        $empresa = $this->empresas->obtenerPropia();
        $mayor = $this->reportes->libroMayor($empresa->id, $cuentaId, $desde, $hasta);
        $cuenta = $mayor['cuenta'];

        $saldo = $mayor['saldo_inicial'];
        $totalDebito = Dinero::cero();
        $totalCredito = Dinero::cero();
        $filas = [[
            'fecha' => null,
            'comprobante' => null,
            'descripcion' => 'Saldo inicial',
            'tercero' => null,
            'debito' => null,
            'credito' => null,
            'saldo' => $saldo,
        ]];

        foreach ($mayor['movimientos'] as $movimiento) {
            $saldo = $cuenta->naturaleza === NaturalezaCuenta::Debito
                ? $saldo->sumar($movimiento->debito)->restar($movimiento->credito)
                : $saldo->sumar($movimiento->credito)->restar($movimiento->debito);
            $totalDebito = $totalDebito->sumar($movimiento->debito);
            $totalCredito = $totalCredito->sumar($movimiento->credito);

            $filas[] = [
                'fecha' => Carbon::parse($movimiento->fecha),
                'comprobante' => "{$movimiento->tipo}-{$movimiento->consecutivo}",
                'descripcion' => $movimiento->descripcion,
                'tercero' => $movimiento->tercero,
                'debito' => $movimiento->debito,
                'credito' => $movimiento->credito,
                'saldo' => $saldo,
            ];
        }

        $documento = new InformeContablePdf(
            titulo: 'Libro mayor',
            nombreArchivo: 'libro-mayor-'.Str::slug($cuenta->codigo)."-{$desde}-{$hasta}",
            alcance: "{$cuenta->codigo} {$cuenta->nombre}",
            fechasInforme: [new FechaDocumento('Desde', Carbon::parse($desde)), new FechaDocumento('Hasta', Carbon::parse($hasta))],
            tituloTabla: 'Movimientos',
            columnas: [
                new ColumnaDocumento('fecha', 'Fecha', TipoColumnaDocumento::Fecha, 11),
                new ColumnaDocumento('comprobante', 'Comprobante', TipoColumnaDocumento::Texto, 13),
                new ColumnaDocumento('descripcion', 'Detalle', TipoColumnaDocumento::Texto, 25),
                new ColumnaDocumento('tercero', 'Tercero', TipoColumnaDocumento::Texto, 14),
                new ColumnaDocumento('debito', 'Débito', TipoColumnaDocumento::Monetario, 12),
                new ColumnaDocumento('credito', 'Crédito', TipoColumnaDocumento::Monetario, 12),
                new ColumnaDocumento('saldo', 'Saldo', TipoColumnaDocumento::Monetario, 13),
            ],
            filas: $filas,
            totales: [
                new TotalDocumento('Total débitos', $totalDebito),
                new TotalDocumento('Total créditos', $totalCredito),
                new TotalDocumento('Saldo final', $saldo),
            ],
        );

        return $this->generar($documento, $empresa, 'Libro mayor', "cuenta {$cuenta->codigo}");
    }

    public function estadosFinancieros(string $desde, string $hasta): PdfGenerado
    {
        $empresa = $this->empresas->obtenerPropia();
        $informe = $this->reportes->estadosFinancieros($empresa->id, $desde, $hasta);
        $situacion = $informe['situacion_financiera'];
        $resultados = $informe['estado_resultados'];

        $columnas = [
            new ColumnaDocumento('cuenta', 'Cuenta', TipoColumnaDocumento::Texto, 75),
            new ColumnaDocumento('valor', 'Valor', TipoColumnaDocumento::Monetario, 25),
        ];
        $filasDe = fn ($coleccion) => $coleccion->map(fn ($fila) => [
            'cuenta' => "{$fila['codigo']} {$fila['nombre']}",
            'valor' => $fila['valor'],
        ])->all();
        $tabla = fn (string $titulo, array $filas, Dinero $total, string $etiquetaTotal) => new TablaDocumento(
            titulo: $titulo,
            columnas: $columnas,
            filas: $filas,
            filaTotal: ['cuenta' => $etiquetaTotal, 'valor' => $total],
            mensajeVacio: 'Sin movimientos en este grupo.',
        );

        $documento = new InformeContablePdf(
            titulo: 'Estados financieros',
            nombreArchivo: "estados-financieros-{$desde}-{$hasta}",
            alcance: 'Situación financiera al corte y resultados del período',
            fechasInforme: [new FechaDocumento('Desde', Carbon::parse($desde)), new FechaDocumento('Hasta', Carbon::parse($hasta))],
            tituloTabla: 'Activos',
            columnas: $columnas,
            filas: [],
            totales: [
                new TotalDocumento('Utilidad del período', $resultados['utilidad']),
            ],
            tablas: [
                $tabla('Activos', $filasDe($situacion['activos']), $situacion['total_activos'], 'Total activos'),
                $tabla('Pasivos', $filasDe($situacion['pasivos']), $situacion['total_pasivos'], 'Total pasivos'),
                $tabla('Patrimonio', $filasDe($situacion['patrimonio']), $situacion['total_patrimonio'], 'Total patrimonio'),
                $tabla('Ingresos', $filasDe($resultados['ingresos']), $resultados['total_ingresos'], 'Total ingresos'),
                $tabla('Gastos y costos', $filasDe($resultados['gastos_costos']), $resultados['total_gastos'], 'Total gastos y costos'),
            ],
            secciones: [
                new SeccionDocumento('Cuadre', $situacion['cuadra']
                    ? 'Activo = pasivo + patrimonio + utilidad del período'
                    : 'NO CUADRA: el activo no iguala pasivo + patrimonio + utilidad'),
            ],
        );

        return $this->generar($documento, $empresa, 'Estados financieros', "del {$desde} al {$hasta}");
    }

    public function auxiliar(string $desde, string $hasta, ?int $cuentaId, ?int $terceroId, string $alcance): PdfGenerado
    {
        $empresa = $this->empresas->obtenerPropia();
        $auxiliar = $this->auxiliares->auxiliar($empresa->id, $desde, $hasta, $cuentaId, $terceroId);

        $resumen = collect($auxiliar['grupos'])->map(fn (array $grupo) => [
            'cuenta' => "{$grupo['cuenta']['codigo']} {$grupo['cuenta']['nombre']}",
            'saldo_inicial' => $grupo['saldo_inicial'],
            'debito' => $grupo['total_debito'],
            'credito' => $grupo['total_credito'],
            'saldo_final' => $grupo['saldo_final'],
        ])->all();

        $detalle = collect($auxiliar['grupos'])->map(fn (array $grupo) => new TablaDocumento(
            titulo: "{$grupo['cuenta']['codigo']} {$grupo['cuenta']['nombre']}",
            columnas: [
                new ColumnaDocumento('fecha', 'Fecha', TipoColumnaDocumento::Fecha, 11),
                new ColumnaDocumento('comprobante', 'Comprobante', TipoColumnaDocumento::Texto, 13),
                new ColumnaDocumento('descripcion', 'Detalle', TipoColumnaDocumento::Texto, 24),
                new ColumnaDocumento('tercero', 'Tercero', TipoColumnaDocumento::Texto, 14),
                new ColumnaDocumento('debito', 'Débito', TipoColumnaDocumento::Monetario, 12),
                new ColumnaDocumento('credito', 'Crédito', TipoColumnaDocumento::Monetario, 12),
                new ColumnaDocumento('saldo', 'Saldo', TipoColumnaDocumento::Monetario, 14),
            ],
            filas: collect($grupo['movimientos'])->map(fn (array $fila) => [
                ...$fila,
                'fecha' => $fila['fecha'] ? Carbon::parse($fila['fecha']) : null,
            ])->all(),
            filaTotal: ['descripcion' => 'Totales', 'debito' => $grupo['total_debito'], 'credito' => $grupo['total_credito'], 'saldo' => $grupo['saldo_final']],
            mensajeVacio: 'Sin movimientos en el período.',
        ))->all();

        $documento = new InformeContablePdf(
            titulo: 'Auxiliar contable',
            nombreArchivo: 'auxiliar-'.($terceroId ? 'tercero' : 'cuenta')."-{$desde}-{$hasta}",
            alcance: $alcance,
            fechasInforme: [new FechaDocumento('Desde', Carbon::parse($desde)), new FechaDocumento('Hasta', Carbon::parse($hasta))],
            tituloTabla: 'Resumen por cuenta',
            columnas: [
                new ColumnaDocumento('cuenta', 'Cuenta', TipoColumnaDocumento::Texto, 34),
                new ColumnaDocumento('saldo_inicial', 'Saldo inicial', TipoColumnaDocumento::Monetario, 17),
                new ColumnaDocumento('debito', 'Débito', TipoColumnaDocumento::Monetario, 16),
                new ColumnaDocumento('credito', 'Crédito', TipoColumnaDocumento::Monetario, 16),
                new ColumnaDocumento('saldo_final', 'Saldo final', TipoColumnaDocumento::Monetario, 17),
            ],
            filas: $resumen,
            totales: [
                new TotalDocumento('Total débitos', $auxiliar['total_debito']),
                new TotalDocumento('Total créditos', $auxiliar['total_credito']),
            ],
            tablas: $detalle,
        );

        return $this->generar($documento, $empresa, 'Auxiliar contable', $alcance);
    }

    private function cuadre(string $etiqueta, Dinero $debito, Dinero $credito): SeccionDocumento
    {
        return new SeccionDocumento($etiqueta, $debito->igualA($credito)
            ? 'Débitos iguales a créditos'
            : 'NO CUADRA: débitos y créditos difieren');
    }

    private function generar(InformeContablePdf $documento, $empresa, string $nombre, string $detalle): PdfGenerado
    {
        $pdf = new PdfGenerado($this->generador->generar($documento, $empresa), $documento->nombreArchivo());

        $this->auditoria->registrar(
            AccionAuditoria::Exportar,
            AuditoriaConstants::MODULO_CONTABILIDAD,
            "{$nombre} ({$detalle}) descargado en PDF",
        );

        return $pdf;
    }
}
