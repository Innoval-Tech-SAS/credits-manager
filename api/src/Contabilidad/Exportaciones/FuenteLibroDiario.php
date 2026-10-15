<?php

namespace App\Contabilidad\Exportaciones;

use App\Contabilidad\Repositories\AsientoContableRepository;
use App\Contabilidad\Support\NumeroComprobante;
use App\Core\Dinero\Dinero;
use App\Exportacion\Contracts\FuenteExportable;
use App\Exportacion\Data\ColumnaExportable;
use App\Exportacion\Enums\TipoColumna;
use App\Exportacion\Services\AccesoExportaciones;
use App\Usuarios\Models\Usuario;

/**
 * Libro diario a Excel/CSV. Serializable: solo lleva la empresa y el rango; las filas se leen con cursor
 * desde el worker, sin sesión, con `empresa_id` explícito.
 */
final class FuenteLibroDiario implements FuenteExportable
{
    public function __construct(
        private readonly int $empresaId,
        private readonly string $desde,
        private readonly string $hasta,
    ) {}

    public function reporte(): string
    {
        return 'contabilidad.libro_diario';
    }

    public function modulo(): string
    {
        return 'contabilidad';
    }

    public function titulo(): string
    {
        return 'Libro diario';
    }

    public function nombreArchivo(): string
    {
        return "libro-diario-{$this->desde}-{$this->hasta}";
    }

    public function columnas(): array
    {
        return [
            new ColumnaExportable('fecha', 'Fecha', TipoColumna::Fecha),
            new ColumnaExportable('comprobante', 'Comprobante'),
            new ColumnaExportable('cuenta_codigo', 'Cuenta'),
            new ColumnaExportable('cuenta_nombre', 'Nombre de la cuenta'),
            new ColumnaExportable('descripcion', 'Descripción'),
            new ColumnaExportable('tercero', 'Tercero'),
            new ColumnaExportable('debito', 'Débito', TipoColumna::Monetario),
            new ColumnaExportable('credito', 'Crédito', TipoColumna::Monetario),
        ];
    }

    public function filas(): iterable
    {
        $asientos = app(AsientoContableRepository::class);

        foreach ($asientos->cursorLibroDiario($this->empresaId, $this->desde, $this->hasta) as $movimiento) {
            yield [
                'fecha' => $movimiento->fecha,
                'comprobante' => NumeroComprobante::formatear($movimiento->tipo, $movimiento->ciclo_numeracion, $movimiento->consecutivo),
                'cuenta_codigo' => $movimiento->cuenta_codigo,
                'cuenta_nombre' => $movimiento->cuenta_nombre,
                'descripcion' => $movimiento->descripcion,
                'tercero' => $movimiento->tercero,
                'debito' => $movimiento->debito,
                'credito' => $movimiento->credito,
            ];
        }

        $totales = $asientos->totalesLibroDiario($this->empresaId, $this->desde, $this->hasta);

        yield [
            'fecha' => null,
            'comprobante' => null,
            'cuenta_codigo' => null,
            'cuenta_nombre' => 'Totales',
            'descripcion' => null,
            'tercero' => null,
            'debito' => Dinero::desdeCentavos($totales['debitos']),
            'credito' => Dinero::desdeCentavos($totales['creditos']),
        ];
    }

    public function totalFilas(): int
    {
        return app(AsientoContableRepository::class)->contarLibroDiario($this->empresaId, $this->desde, $this->hasta) + 1;
    }

    public function filtrosAplicados(): array
    {
        return ['desde' => $this->desde, 'hasta' => $this->hasta];
    }

    public function autorizadoPara(Usuario $usuario): bool
    {
        return $usuario->empresa_id !== null && app(AccesoExportaciones::class)->puede($usuario, $this->reporte())
            && $usuario->tienePermiso('informes_contables', 'listar_libro_diario');
    }
}
