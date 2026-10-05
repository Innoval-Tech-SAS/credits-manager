<?php

namespace App\Cartera\Exportaciones;

use App\Cartera\Services\CarteraService;
use App\Core\Dinero\Dinero;
use App\Exportacion\Contracts\FuenteExportable;
use App\Exportacion\Data\ColumnaExportable;
use App\Exportacion\Enums\TipoColumna;
use App\Usuarios\Models\Usuario;

/**
 * Cartera (créditos desembolsados con su estado de mora) a Excel/CSV. Serializable: solo lleva la empresa
 * y el asesor cuyo alcance limita los datos (null = toda la empresa).
 */
final class FuenteCartera implements FuenteExportable
{
    public function __construct(
        private readonly int $empresaId,
        private readonly ?int $asesorId,
    ) {}

    public function reporte(): string
    {
        return 'cartera.listado';
    }

    public function modulo(): string
    {
        return 'cartera';
    }

    public function titulo(): string
    {
        return 'Cartera';
    }

    public function nombreArchivo(): string
    {
        return 'cartera-'.now('America/Bogota')->format('Y-m-d');
    }

    public function columnas(): array
    {
        return [
            new ColumnaExportable('credito', 'Crédito (id)', TipoColumna::Entero),
            new ColumnaExportable('cliente', 'Cliente'),
            new ColumnaExportable('estado', 'Estado'),
            new ColumnaExportable('dias_mora', 'Días de mora', TipoColumna::Entero),
            new ColumnaExportable('saldo', 'Saldo', TipoColumna::Monetario),
        ];
    }

    public function filas(): iterable
    {
        $filas = $this->filasDeCartera();
        $total = Dinero::cero();

        foreach ($filas as $fila) {
            $total = $total->sumar($fila['saldo']);

            yield $fila;
        }

        yield [
            'credito' => null,
            'cliente' => 'Total',
            'estado' => null,
            'dias_mora' => null,
            'saldo' => $total,
        ];
    }

    public function totalFilas(): int
    {
        return count($this->filasDeCartera()) + 1;
    }

    public function filtrosAplicados(): array
    {
        return ['asesor_id' => $this->asesorId];
    }

    public function autorizadoPara(Usuario $usuario): bool
    {
        return $usuario->empresa_id !== null && $usuario->tienePermiso('cartera', 'exportar');
    }

    /** @return list<array{credito: int, cliente: string, estado: string, dias_mora: ?int, saldo: Dinero}> */
    private function filasDeCartera(): array
    {
        return app(CarteraService::class)->filasParaExportar($this->empresaId, $this->asesorId);
    }
}
