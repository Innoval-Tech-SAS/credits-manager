<?php

namespace App\Contabilidad\Exportaciones;

use App\Contabilidad\Services\AuxiliarContableService;
use App\Exportacion\Contracts\FuenteExportable;
use App\Exportacion\Data\ColumnaExportable;
use App\Exportacion\Enums\TipoColumna;
use App\Usuarios\Models\Usuario;

/**
 * Auxiliar por cuenta o por tercero a Excel/CSV. Una fila de saldo inicial y una de totales por cuenta, con los
 * movimientos entre ellas. Serializable: empresa, filtros y rango; el cálculo se hace en el worker con `empresa_id`
 * explícito.
 */
final class FuenteAuxiliarContable implements FuenteExportable
{
    public function __construct(
        private readonly int $empresaId,
        private readonly string $desde,
        private readonly string $hasta,
        private readonly ?int $cuentaId,
        private readonly ?int $terceroId,
    ) {}

    public function reporte(): string
    {
        return 'contabilidad.auxiliar';
    }

    public function modulo(): string
    {
        return 'contabilidad';
    }

    public function titulo(): string
    {
        return $this->terceroId ? 'Auxiliar por tercero' : 'Auxiliar por cuenta';
    }

    public function nombreArchivo(): string
    {
        return 'auxiliar-'.($this->terceroId ? 'tercero' : 'cuenta')."-{$this->desde}-{$this->hasta}";
    }

    public function columnas(): array
    {
        return [
            new ColumnaExportable('cuenta', 'Cuenta'),
            new ColumnaExportable('fecha', 'Fecha', TipoColumna::Fecha),
            new ColumnaExportable('comprobante', 'Comprobante'),
            new ColumnaExportable('descripcion', 'Detalle'),
            new ColumnaExportable('tercero', 'Tercero'),
            new ColumnaExportable('debito', 'Débito', TipoColumna::Monetario),
            new ColumnaExportable('credito', 'Crédito', TipoColumna::Monetario),
            new ColumnaExportable('saldo', 'Saldo', TipoColumna::Monetario),
        ];
    }

    public function filas(): iterable
    {
        foreach (app(AuxiliarContableService::class)->auxiliar($this->empresaId, $this->desde, $this->hasta, $this->cuentaId, $this->terceroId)['grupos'] as $grupo) {
            $cuenta = "{$grupo['cuenta']['codigo']} {$grupo['cuenta']['nombre']}";

            yield ['cuenta' => $cuenta, 'descripcion' => 'Saldo inicial', 'saldo' => $grupo['saldo_inicial']];

            foreach ($grupo['movimientos'] as $movimiento) {
                yield [
                    'cuenta' => $cuenta,
                    'fecha' => $movimiento['fecha'],
                    'comprobante' => $movimiento['comprobante'],
                    'descripcion' => $movimiento['descripcion'],
                    'tercero' => $movimiento['tercero'],
                    'debito' => $movimiento['debito'],
                    'credito' => $movimiento['credito'],
                    'saldo' => $movimiento['saldo'],
                ];
            }

            yield [
                'cuenta' => $cuenta,
                'descripcion' => 'Totales del período',
                'debito' => $grupo['total_debito'],
                'credito' => $grupo['total_credito'],
                'saldo' => $grupo['saldo_final'],
            ];
        }
    }

    public function totalFilas(): int
    {
        $grupos = app(AuxiliarContableService::class)->auxiliar($this->empresaId, $this->desde, $this->hasta, $this->cuentaId, $this->terceroId)['grupos'];

        return collect($grupos)->sum(fn (array $grupo) => count($grupo['movimientos']) + 2);
    }

    public function filtrosAplicados(): array
    {
        return [
            'cuenta_id' => $this->cuentaId,
            'tercero_id' => $this->terceroId,
            'desde' => $this->desde,
            'hasta' => $this->hasta,
        ];
    }

    public function autorizadoPara(Usuario $usuario): bool
    {
        return $usuario->empresa_id !== null && $usuario->tienePermiso('informes_contables', 'exportar');
    }
}
