<?php

namespace App\Clientes\Exportaciones;

use App\Clientes\Repositories\ClienteRepository;
use App\Creditos\Enums\EstadoCredito;
use App\Exportacion\Contracts\FuenteExportable;
use App\Exportacion\Data\ColumnaExportable;
use App\Exportacion\Enums\TipoColumna;
use App\Exportacion\Services\AccesoExportaciones;
use App\Usuarios\Models\Usuario;

/**
 * Listado de clientes a Excel/CSV, con los mismos filtros de la pantalla. Serializable: empresa, asesor con
 * alcance (null = toda la empresa), búsqueda, activo y estado de crédito; las filas se leen con cursor en el worker.
 */
final class FuenteClientes implements FuenteExportable
{
    public function __construct(
        private readonly int $empresaId,
        private readonly ?int $asesorId,
        private readonly ?string $busqueda,
        private readonly ?bool $activo,
        private readonly ?string $estadoCredito,
    ) {}

    public function reporte(): string
    {
        return 'clientes.listado';
    }

    public function modulo(): string
    {
        return 'clientes';
    }

    public function titulo(): string
    {
        return 'Clientes';
    }

    public function nombreArchivo(): string
    {
        return 'clientes-'.now('America/Bogota')->format('Y-m-d');
    }

    public function columnas(): array
    {
        return [
            new ColumnaExportable('id', 'Cliente (id)', TipoColumna::Entero),
            new ColumnaExportable('tipo_documento', 'Tipo de documento'),
            new ColumnaExportable('numero_documento', 'Número de documento'),
            new ColumnaExportable('nombre', 'Nombre'),
            new ColumnaExportable('celular', 'Celular'),
            new ColumnaExportable('correo', 'Correo'),
            new ColumnaExportable('ciudad', 'Ciudad'),
            new ColumnaExportable('asesor', 'Asesor'),
            new ColumnaExportable('empleador', 'Empleador'),
            new ColumnaExportable('activo', 'Activo', TipoColumna::Booleano),
        ];
    }

    public function filas(): iterable
    {
        foreach (app(ClienteRepository::class)->cursorParaExportar($this->empresaId, $this->asesorId, $this->busqueda, $this->activo, $this->estadoEnum()) as $cliente) {
            yield [
                'id' => $cliente->id,
                'tipo_documento' => $cliente->tercero->tipo_documento?->value,
                'numero_documento' => $cliente->tercero->numero_documento,
                'nombre' => $cliente->tercero->nombre,
                'celular' => $cliente->tercero->celular,
                'correo' => $cliente->tercero->correo,
                'ciudad' => $cliente->tercero->ciudad,
                'asesor' => $cliente->asesor?->nombre,
                'empleador' => $cliente->empleador?->nombre,
                'activo' => (bool) $cliente->activo,
            ];
        }
    }

    public function totalFilas(): int
    {
        return app(ClienteRepository::class)->contarParaExportar($this->empresaId, $this->asesorId, $this->busqueda, $this->activo, $this->estadoEnum());
    }

    public function filtrosAplicados(): array
    {
        return ['busqueda' => $this->busqueda, 'activo' => $this->activo, 'estado_credito' => $this->estadoCredito, 'asesor_id' => $this->asesorId];
    }

    public function autorizadoPara(Usuario $usuario): bool
    {
        return $usuario->empresa_id !== null && app(AccesoExportaciones::class)->puede($usuario, $this->reporte());
    }

    private function estadoEnum(): ?EstadoCredito
    {
        return $this->estadoCredito === null ? null : EstadoCredito::from($this->estadoCredito);
    }
}
