<?php

namespace App\Contabilidad\Exportaciones;

use App\Contabilidad\Services\ReporteContableService;
use App\Core\Dinero\Dinero;
use App\Exportacion\Contracts\FuenteExportable;
use App\Exportacion\Data\ColumnaExportable;
use App\Exportacion\Enums\TipoColumna;
use App\Usuarios\Models\Usuario;
use Illuminate\Support\Collection;

/** Balance de prueba a Excel/CSV, con fila de totales. Serializable: empresa y fecha de corte. */
final class FuenteBalancePrueba implements FuenteExportable
{
    public function __construct(
        private readonly int $empresaId,
        private readonly string $hasta,
    ) {}

    public function reporte(): string
    {
        return 'contabilidad.balance_prueba';
    }

    public function modulo(): string
    {
        return 'contabilidad';
    }

    public function titulo(): string
    {
        return 'Balance de prueba';
    }

    public function nombreArchivo(): string
    {
        return "balance-de-prueba-{$this->hasta}";
    }

    public function columnas(): array
    {
        return [
            new ColumnaExportable('codigo', 'Cuenta'),
            new ColumnaExportable('nombre', 'Nombre de la cuenta'),
            new ColumnaExportable('debitos', 'Débitos', TipoColumna::Monetario),
            new ColumnaExportable('creditos', 'Créditos', TipoColumna::Monetario),
            new ColumnaExportable('saldo', 'Saldo', TipoColumna::Monetario),
        ];
    }

    public function filas(): iterable
    {
        $balance = $this->balance();

        foreach ($balance['saldos'] as $saldo) {
            yield $saldo;
        }

        yield [
            'codigo' => null,
            'nombre' => 'Totales',
            'debitos' => $balance['total_debito'],
            'creditos' => $balance['total_credito'],
            'saldo' => null,
        ];
    }

    public function totalFilas(): int
    {
        return count($this->balance()['saldos']) + 1;
    }

    public function filtrosAplicados(): array
    {
        return ['hasta' => $this->hasta];
    }

    public function autorizadoPara(Usuario $usuario): bool
    {
        return $usuario->empresa_id !== null && $usuario->tienePermiso('informes_contables', 'exportar');
    }

    /** @return array{saldos: Collection, total_debito: Dinero, total_credito: Dinero} */
    private function balance(): array
    {
        return app(ReporteContableService::class)->balancePrueba($this->empresaId, $this->hasta);
    }
}
