<?php

namespace App\Contabilidad\Exportaciones;

use App\Contabilidad\Services\ReporteContableService;
use App\Core\Dinero\Dinero;
use App\Exportacion\Contracts\FuenteExportable;
use App\Exportacion\Data\ColumnaExportable;
use App\Exportacion\Enums\TipoColumna;
use App\Usuarios\Models\Usuario;

/**
 * Estado de resultados y situación financiera en una sola hoja: una fila por cuenta con su sección y
 * una fila de total por sección. Serializable: empresa y rango.
 */
final class FuenteEstadosFinancieros implements FuenteExportable
{
    public function __construct(
        private readonly int $empresaId,
        private readonly string $desde,
        private readonly string $hasta,
    ) {}

    public function reporte(): string
    {
        return 'contabilidad.estados_financieros';
    }

    public function modulo(): string
    {
        return 'contabilidad';
    }

    public function titulo(): string
    {
        return 'Estados financieros';
    }

    public function nombreArchivo(): string
    {
        return "estados-financieros-{$this->desde}-{$this->hasta}";
    }

    public function columnas(): array
    {
        return [
            new ColumnaExportable('seccion', 'Sección'),
            new ColumnaExportable('codigo', 'Cuenta'),
            new ColumnaExportable('nombre', 'Nombre'),
            new ColumnaExportable('valor', 'Valor', TipoColumna::Monetario),
        ];
    }

    public function filas(): iterable
    {
        yield from $this->filasDelReporte();
    }

    public function totalFilas(): int
    {
        return count($this->filasDelReporte());
    }

    public function filtrosAplicados(): array
    {
        return ['desde' => $this->desde, 'hasta' => $this->hasta];
    }

    public function autorizadoPara(Usuario $usuario): bool
    {
        return $usuario->empresa_id !== null && $usuario->tienePermiso('informes_contables', 'exportar')
            && $usuario->tienePermiso('informes_contables', 'listar_estados_financieros');
    }

    /** @return list<array{seccion: string, codigo: ?string, nombre: string, valor: Dinero}> */
    private function filasDelReporte(): array
    {
        $estados = app(ReporteContableService::class)->estadosFinancieros($this->empresaId, $this->desde, $this->hasta);
        $resultados = $estados['estado_resultados'];
        $situacion = $estados['situacion_financiera'];

        $filas = [];

        $cuentas = function (string $seccion, iterable $items) use (&$filas): void {
            foreach ($items as $cuenta) {
                $filas[] = ['seccion' => $seccion, 'codigo' => $cuenta['codigo'], 'nombre' => $cuenta['nombre'], 'valor' => $cuenta['valor']];
            }
        };
        $total = function (string $seccion, string $etiqueta, Dinero $valor) use (&$filas): void {
            $filas[] = ['seccion' => $seccion, 'codigo' => null, 'nombre' => $etiqueta, 'valor' => $valor];
        };

        $cuentas('Ingresos', $resultados['ingresos']);
        $total('Ingresos', 'Total ingresos', $resultados['total_ingresos']);
        $cuentas('Gastos y costos', $resultados['gastos_costos']);
        $total('Gastos y costos', 'Total gastos y costos', $resultados['total_gastos']);
        $total('Resultado', 'Utilidad del período', $resultados['utilidad']);

        $cuentas('Activos', $situacion['activos']);
        $total('Activos', 'Total activos', $situacion['total_activos']);
        $cuentas('Pasivos', $situacion['pasivos']);
        $total('Pasivos', 'Total pasivos', $situacion['total_pasivos']);
        $cuentas('Patrimonio', $situacion['patrimonio']);
        $total('Patrimonio', 'Total patrimonio', $situacion['total_patrimonio']);

        return $filas;
    }
}
