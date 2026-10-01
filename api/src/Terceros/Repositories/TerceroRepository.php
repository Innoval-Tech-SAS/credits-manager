<?php

namespace App\Terceros\Repositories;

use App\Core\Base\BaseRepository;
use App\Core\Scopes\AlcanceAsesorScope;
use App\Terceros\Models\Tercero;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class TerceroRepository extends BaseRepository
{
    public function __construct(Tercero $tercero)
    {
        parent::__construct($tercero);
    }

    public function activos(): Collection
    {
        return $this->consulta()->where('activo', true)->orderBy('nombre')->get();
    }

    public function paginar(?string $busqueda, ?string $tipo, int $porPagina, int $pagina): LengthAwarePaginator
    {
        return $this->consulta()
            ->with(['cliente', 'proveedor', 'otroTercero'])
            ->when($busqueda, fn ($query) => $query->where(fn ($sub) => $sub
                ->where('nombre', 'like', "%{$busqueda}%")
                ->orWhere('numero_documento', 'like', "%{$busqueda}%")))
            ->when($tipo, fn ($query) => $query->whereHas($this->relacionParaTipo($tipo)))
            ->orderBy('nombre')
            ->paginate($porPagina, ['*'], 'page', $pagina);
    }

    /**
     * Excepción declarada al alcance por asesor: para saber si el documento ya es cliente hay que ver los
     * clientes de todos los asesores; si no, un asesor podría intentar duplicar un cliente ajeno.
     */
    public function buscarPorDocumento(string $tipoDocumento, string $numeroDocumento): ?Tercero
    {
        return $this->consulta()
            ->with(['cliente' => fn ($consulta) => $consulta->withoutGlobalScope(AlcanceAsesorScope::class)])
            ->where('tipo_documento', $tipoDocumento)
            ->where('numero_documento', $numeroDocumento)
            ->first();
    }

    /** Reutiliza el tercero existente con ese documento (sin tocar sus datos) o lo crea si no existe. */
    public function obtenerOCrear(array $datos): Tercero
    {
        return $this->consulta()->firstOrCreate(
            ['tipo_documento' => $datos['tipo_documento'], 'numero_documento' => $datos['numero_documento']],
            $datos,
        );
    }

    /**
     * Precarga en pocas consultas (una por `tipo_documento` distinto) los terceros existentes
     * para un lote de pares documento, junto con sus extensiones (cliente/proveedor/otro) ya
     * cargadas — para evitar un `firstOrCreate` + lazy-load por fila en importaciones masivas.
     *
     * @param  array<array{tipo_documento: string, numero_documento: string}>  $pares
     */
    public function porDocumentos(array $pares): Collection
    {
        $resultado = new Collection;

        foreach (collect($pares)->groupBy('tipo_documento') as $tipoDocumento => $paresDelTipo) {
            $numeros = $paresDelTipo->pluck('numero_documento')->unique()->all();

            $resultado = $resultado->merge(
                $this->consulta()
                    ->where('tipo_documento', $tipoDocumento)
                    ->whereIn('numero_documento', $numeros)
                    ->with(['cliente', 'proveedor', 'otroTercero'])
                    ->get()
            );
        }

        return $resultado;
    }

    private function relacionParaTipo(string $tipo): string
    {
        return match ($tipo) {
            'cliente' => 'cliente',
            'proveedor' => 'proveedor',
            default => 'otroTercero',
        };
    }
}
