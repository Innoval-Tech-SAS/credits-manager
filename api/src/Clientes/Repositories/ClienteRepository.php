<?php

namespace App\Clientes\Repositories;

use App\Clientes\Models\Cliente;
use App\Core\Base\BaseRepository;
use App\Creditos\Enums\EstadoCredito;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\LazyCollection;

class ClienteRepository extends BaseRepository
{
    public function __construct(Cliente $cliente)
    {
        parent::__construct($cliente);
    }

    public function paginar(
        ?string $busqueda,
        ?string $columna,
        string $direccion,
        int $porPagina,
        int $pagina,
        ?bool $activo = null,
        ?EstadoCredito $estadoCredito = null,
    ): LengthAwarePaginator {
        $consulta = $this->consulta()->with(['tercero', 'empleador', 'asesor:id,nombre']);

        $this->filtrar($consulta, $busqueda, $activo, $estadoCredito);

        if (in_array($columna, ['nombre', 'numero_documento'], true)) {
            $consulta->join('terceros', 'terceros.id', '=', 'clientes.tercero_id')
                ->orderBy("terceros.{$columna}", $direccion)
                ->select('clientes.*');
        } elseif ($columna === 'empleador') {
            $consulta->leftJoin('empleadores', 'empleadores.id', '=', 'clientes.empleador_id')
                ->orderBy('empleadores.nombre', $direccion)
                ->select('clientes.*');
        } elseif ($columna) {
            $consulta->orderBy($columna, $direccion);
        }

        return $consulta->paginate(perPage: $porPagina, page: $pagina);
    }

    /**
     * Mismo listado que la pantalla, con empresa y asesor explícitos, para exportar. Ordenado por nombre del tercero.
     */
    public function consultaParaExportar(int $empresaId, ?int $asesorId, ?string $busqueda, ?bool $activo, ?EstadoCredito $estadoCredito): Builder
    {
        $consulta = $this->consulta()
            ->where('clientes.empresa_id', $empresaId)
            ->with(['tercero', 'empleador', 'asesor:id,nombre'])
            ->join('terceros', 'terceros.id', '=', 'clientes.tercero_id')
            ->orderBy('terceros.nombre')
            ->select('clientes.*');

        if ($asesorId !== null) {
            $consulta->where('clientes.asesor_id', $asesorId);
        }

        return $this->filtrar($consulta, $busqueda, $activo, $estadoCredito);
    }

    public function cursorParaExportar(int $empresaId, ?int $asesorId, ?string $busqueda, ?bool $activo, ?EstadoCredito $estadoCredito): LazyCollection
    {
        return $this->consultaParaExportar($empresaId, $asesorId, $busqueda, $activo, $estadoCredito)->lazy();
    }

    public function contarParaExportar(int $empresaId, ?int $asesorId, ?string $busqueda, ?bool $activo, ?EstadoCredito $estadoCredito): int
    {
        return $this->consultaParaExportar($empresaId, $asesorId, $busqueda, $activo, $estadoCredito)->reorder()->count();
    }

    private function filtrar(Builder $consulta, ?string $busqueda, ?bool $activo, ?EstadoCredito $estadoCredito): Builder
    {
        if ($activo !== null) {
            $consulta->where('clientes.activo', $activo);
        }

        if ($estadoCredito) {
            $consulta->whereHas('creditos', fn (Builder $subconsulta) => $subconsulta->where('estado', $estadoCredito));
        }

        if ($busqueda) {
            $consulta->where(function (Builder $subconsulta) use ($busqueda) {
                $subconsulta->whereHas('tercero', fn (Builder $subconsultaTercero) => $subconsultaTercero
                    ->where('nombre', 'like', "%{$busqueda}%")
                    ->orWhere('numero_documento', 'like', "%{$busqueda}%"))
                    ->orWhereHas('empleador', fn (Builder $subconsultaEmpleador) => $subconsultaEmpleador->where('nombre', 'like', "%{$busqueda}%"));
            });
        }

        return $consulta;
    }

    public function contarActivos(): int
    {
        return $this->consulta()->where('activo', true)->count();
    }
}
