<?php

namespace App\Creditos\Repositories;

use App\Core\Base\BaseRepository;
use App\Creditos\Enums\EstadoCredito;
use App\Creditos\Enums\EstadoCuota;
use App\Creditos\Models\Credito;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class CreditoRepository extends BaseRepository
{
    public function __construct(Credito $credito)
    {
        parent::__construct($credito);
    }

    public function activosConCliente(): Collection
    {
        return $this->consulta()
            ->where('estado', EstadoCredito::Desembolsado)
            ->with('cliente.tercero')
            ->get()
            ->sortBy(fn (Credito $credito) => $credito->cliente->tercero->nombre)
            ->values();
    }

    public function contarActivos(): int
    {
        return $this->consulta()->where('estado', EstadoCredito::Desembolsado)->count();
    }

    public function paginar(?string $busqueda, int $porPagina, int $pagina, ?int $clienteId = null, ?EstadoCredito $estado = null): LengthAwarePaginator
    {
        $consulta = $this->consulta()
            ->with('cliente.tercero')
            ->withCount(['cuotas as cuotas_vencidas' => fn ($q) => $q->where('estado', EstadoCuota::Vencida)])
            ->withCount('pagos')
            ->orderByDesc('creado_en');

        if ($busqueda) {
            $consulta->whereHas('cliente.tercero', fn ($q) => $q->where('nombre', 'like', "%{$busqueda}%"));
        }

        if ($clienteId) {
            $consulta->where('cliente_id', $clienteId);
        }

        if ($estado) {
            $consulta->where('estado', $estado);
        }

        return $consulta->paginate(perPage: $porPagina, page: $pagina);
    }

    public function obtenerConRelaciones(Credito $credito): Credito
    {
        return $credito->load(['cliente.tercero', 'cuotas', 'credito_origen.cliente.tercero']);
    }

    public function marcarComoPagado(Credito $credito): void
    {
        $this->actualizar($credito, ['estado' => EstadoCredito::Pagado]);
    }

    public function marcarComoRefinanciado(Credito $credito): void
    {
        $this->actualizar($credito, ['estado' => EstadoCredito::Refinanciado]);
    }

    /** Bloquea la fila del original: dos desembolsos simultáneos no pueden refinanciar el mismo crédito. */
    public function buscarParaCongelar(int $id): Credito
    {
        return $this->consulta()->lockForUpdate()->findOrFail($id);
    }

    public function existeRefinanciacionEnCurso(int $origenId): bool
    {
        return $this->consulta()
            ->where('credito_origen_id', $origenId)
            ->whereIn('estado', [EstadoCredito::Solicitado, EstadoCredito::Aprobado])
            ->exists();
    }
}
