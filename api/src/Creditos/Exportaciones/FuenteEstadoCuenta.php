<?php

namespace App\Creditos\Exportaciones;

use App\Core\Dinero\Dinero;
use App\Creditos\Repositories\CreditoRepository;
use App\Creditos\Repositories\CuotaRepository;
use App\Exportacion\Contracts\FuenteExportable;
use App\Exportacion\Data\ColumnaExportable;
use App\Exportacion\Enums\TipoColumna;
use App\Usuarios\Models\Usuario;

/**
 * Plan de cuotas de un crédito con lo pagado y el saldo, a Excel/CSV. Las mismas columnas del estado de cuenta
 * en PDF. Serializable: empresa y crédito; el crédito se vuelve a validar contra la empresa en el worker.
 */
final class FuenteEstadoCuenta implements FuenteExportable
{
    public function __construct(
        private readonly int $empresaId,
        private readonly int $creditoId,
    ) {}

    public function reporte(): string
    {
        return 'creditos.estado_cuenta';
    }

    public function modulo(): string
    {
        return 'creditos';
    }

    public function titulo(): string
    {
        return 'Estado de cuenta del crédito';
    }

    public function nombreArchivo(): string
    {
        return "estado-de-cuenta-credito-{$this->creditoId}";
    }

    public function columnas(): array
    {
        return [
            new ColumnaExportable('numero', 'Cuota', TipoColumna::Entero),
            new ColumnaExportable('vencimiento', 'Vencimiento', TipoColumna::Fecha),
            new ColumnaExportable('interes', 'Interés', TipoColumna::Monetario),
            new ColumnaExportable('capital', 'Capital', TipoColumna::Monetario),
            new ColumnaExportable('cuota', 'Valor de la cuota', TipoColumna::Monetario),
            new ColumnaExportable('pagado', 'Pagado', TipoColumna::Monetario),
            new ColumnaExportable('saldo_capital', 'Saldo de capital', TipoColumna::Monetario),
            new ColumnaExportable('estado', 'Estado'),
        ];
    }

    public function filas(): iterable
    {
        app(CreditoRepository::class)->buscarDeEmpresaOFallar($this->empresaId, $this->creditoId);

        $total = Dinero::cero();
        $totalPagado = Dinero::cero();

        foreach (app(CuotaRepository::class)->cursorDelCredito($this->creditoId) as $cuota) {
            $total = $total->sumar($cuota->valor);
            $totalPagado = $totalPagado->sumar($cuota->valor_pagado);

            yield [
                'numero' => $cuota->numero_cuota,
                'vencimiento' => $cuota->fecha_vencimiento,
                'interes' => $cuota->valor_interes,
                'capital' => $cuota->valor_capital,
                'cuota' => $cuota->valor,
                'pagado' => $cuota->valor_pagado,
                'saldo_capital' => $cuota->saldo_capital,
                'estado' => $cuota->estado->value,
            ];
        }

        yield [
            'numero' => null,
            'vencimiento' => null,
            'interes' => null,
            'capital' => null,
            'cuota' => $total,
            'pagado' => $totalPagado,
            'saldo_capital' => null,
            'estado' => 'Total',
        ];
    }

    public function totalFilas(): int
    {
        app(CreditoRepository::class)->buscarDeEmpresaOFallar($this->empresaId, $this->creditoId);

        return app(CuotaRepository::class)->cursorDelCredito($this->creditoId)->count() + 1;
    }

    public function filtrosAplicados(): array
    {
        return ['credito_id' => $this->creditoId];
    }

    public function autorizadoPara(Usuario $usuario): bool
    {
        return $usuario->empresa_id !== null && $usuario->tienePermiso('creditos', 'exportar');
    }
}
