<?php

namespace App\Contabilidad\Repositories;

use App\Contabilidad\Enums\EstadoComprobante;
use App\Contabilidad\Models\Comprobante;
use App\Core\Base\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ComprobanteRepository extends BaseRepository
{
    public function __construct(Comprobante $comprobante)
    {
        parent::__construct($comprobante);
    }

    public function paginar(?string $busqueda, int $porPagina, int $pagina): LengthAwarePaginator
    {
        $consulta = $this->consulta()
            ->with('tipoComprobante')
            ->orderByDesc('fecha')
            ->orderByDesc('id');

        if ($busqueda) {
            $consulta->where(function (Builder $subconsulta) use ($busqueda) {
                $subconsulta->where('concepto', 'like', "%{$busqueda}%")
                    ->orWhereHas('tipoComprobante', fn (Builder $subconsultaTipo) => $subconsultaTipo
                        ->where('codigo', 'like', "%{$busqueda}%")
                        ->orWhere('nombre', 'like', "%{$busqueda}%"));
            });
        }

        return $consulta->paginate($porPagina, ['*'], 'page', $pagina);
    }

    public function obtenerConLineas(Comprobante $comprobante): Comprobante
    {
        return $comprobante->load(['tipoComprobante', 'tercero', 'comprobanteReverso', 'lineas.cuenta', 'lineas.centroCosto', 'revisor']);
    }

    public function buscarContabilizadoPorOrigen(string $moduloOrigen, int $origenId): ?Comprobante
    {
        return $this->consulta()
            ->where('modulo_origen', $moduloOrigen)
            ->where('origen_id', $origenId)
            ->where('estado', EstadoComprobante::Contabilizado)
            ->latest('id')
            ->first();
    }
}
