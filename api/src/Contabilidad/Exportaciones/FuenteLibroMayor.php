<?php

namespace App\Contabilidad\Exportaciones;

use App\Contabilidad\Enums\NaturalezaCuenta;
use App\Contabilidad\Repositories\AsientoContableRepository;
use App\Contabilidad\Repositories\PlanCuentaRepository;
use App\Contabilidad\Services\ReporteContableService;
use App\Core\Dinero\Dinero;
use App\Exportacion\Contracts\FuenteExportable;
use App\Exportacion\Data\ColumnaExportable;
use App\Exportacion\Enums\TipoColumna;
use App\Usuarios\Models\Usuario;
use Illuminate\Support\Str;

/**
 * Libro mayor de una cuenta a Excel/CSV, con saldo corrido según la naturaleza de la cuenta.
 * Serializable: lleva empresa, cuenta y rango; el saldo inicial y las filas se leen en el worker con
 * `empresa_id` explícito.
 */
final class FuenteLibroMayor implements FuenteExportable
{
    public function __construct(
        private readonly int $empresaId,
        private readonly int $cuentaId,
        private readonly string $desde,
        private readonly string $hasta,
    ) {}

    public function reporte(): string
    {
        return 'contabilidad.libro_mayor';
    }

    public function modulo(): string
    {
        return 'contabilidad';
    }

    public function titulo(): string
    {
        return 'Libro mayor';
    }

    public function nombreArchivo(): string
    {
        $cuenta = app(PlanCuentaRepository::class)->buscarDeEmpresaOFallar($this->empresaId, $this->cuentaId);

        return 'libro-mayor-'.Str::slug($cuenta->codigo)."-{$this->desde}-{$this->hasta}";
    }

    public function columnas(): array
    {
        return [
            new ColumnaExportable('fecha', 'Fecha', TipoColumna::Fecha),
            new ColumnaExportable('comprobante', 'Comprobante'),
            new ColumnaExportable('descripcion', 'Descripción'),
            new ColumnaExportable('tercero', 'Tercero'),
            new ColumnaExportable('debito', 'Débito', TipoColumna::Monetario),
            new ColumnaExportable('credito', 'Crédito', TipoColumna::Monetario),
            new ColumnaExportable('saldo', 'Saldo', TipoColumna::Monetario),
        ];
    }

    public function filas(): iterable
    {
        $cuenta = app(PlanCuentaRepository::class)->buscarDeEmpresaOFallar($this->empresaId, $this->cuentaId);
        $saldo = app(ReporteContableService::class)->saldoInicialDeCuenta($this->empresaId, $cuenta, $this->desde);

        yield [
            'fecha' => null,
            'comprobante' => null,
            'descripcion' => 'Saldo inicial',
            'tercero' => null,
            'debito' => null,
            'credito' => null,
            'saldo' => $saldo,
        ];

        $totalDebito = Dinero::desdeCentavos(0);
        $totalCredito = Dinero::desdeCentavos(0);

        $movimientos = app(AsientoContableRepository::class)->cursorLibroMayor($this->empresaId, $this->cuentaId, $this->desde, $this->hasta);

        foreach ($movimientos as $movimiento) {
            $saldo = $cuenta->naturaleza === NaturalezaCuenta::Debito
                ? $saldo->sumar($movimiento->debito)->restar($movimiento->credito)
                : $saldo->sumar($movimiento->credito)->restar($movimiento->debito);

            $totalDebito = $totalDebito->sumar($movimiento->debito);
            $totalCredito = $totalCredito->sumar($movimiento->credito);

            yield [
                'fecha' => $movimiento->fecha,
                'comprobante' => "{$movimiento->tipo}-{$movimiento->consecutivo}",
                'descripcion' => $movimiento->descripcion,
                'tercero' => $movimiento->tercero,
                'debito' => $movimiento->debito,
                'credito' => $movimiento->credito,
                'saldo' => $saldo,
            ];
        }

        yield [
            'fecha' => null,
            'comprobante' => null,
            'descripcion' => 'Totales del período',
            'tercero' => null,
            'debito' => $totalDebito,
            'credito' => $totalCredito,
            'saldo' => $saldo,
        ];
    }

    public function totalFilas(): int
    {
        return app(AsientoContableRepository::class)->contarLibroMayor($this->empresaId, $this->cuentaId, $this->desde, $this->hasta) + 2;
    }

    public function filtrosAplicados(): array
    {
        return ['cuenta_id' => $this->cuentaId, 'desde' => $this->desde, 'hasta' => $this->hasta];
    }

    public function autorizadoPara(Usuario $usuario): bool
    {
        return $usuario->empresa_id !== null && $usuario->tienePermiso('informes_contables', 'exportar');
    }
}
