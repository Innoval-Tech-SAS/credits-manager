<?php

namespace App\Pagos\Repositories;

use App\Core\Base\BaseRepository;
use App\Pagos\Models\Pago;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class PagoRepository extends BaseRepository
{
    public function __construct(Pago $pago)
    {
        parent::__construct($pago);
    }

    /** Todos los pagos de la empresa (`PerteneceAEmpresa` en el modelo ya filtra), no
     * atados a un crédito puntual — para el listado global del módulo Pagos. */
    public function paginar(?string $busqueda, int $porPagina, int $pagina): LengthAwarePaginator
    {
        $consulta = $this->consulta()
            ->with(['cliente.tercero', 'credito', 'recibo:id,pago_id,numero,estado'])
            ->orderByDesc('fecha_pago');

        if ($busqueda) {
            $consulta->whereHas('cliente.tercero', fn ($q) => $q->where('nombre', 'like', "%{$busqueda}%"));
        }

        return $consulta->paginate(perPage: $porPagina, page: $pagina);
    }

    public function porCredito(int $creditoId, string $direccion = 'desc'): Collection
    {
        return $this->consulta()
            ->with('recibo:id,pago_id,numero,estado')
            ->where('credito_id', $creditoId)
            ->orderBy('fecha_pago', $direccion)
            ->get();
    }

    public function porCliente(int $clienteId, int $limite = 10): Collection
    {
        return $this->consulta()
            ->with('recibo:id,pago_id,numero,estado')
            ->where('cliente_id', $clienteId)
            ->orderByDesc('fecha_pago')
            ->limit($limite)
            ->get();
    }

    public function ultimoPago(int $creditoId): ?Pago
    {
        return $this->consulta()->where('credito_id', $creditoId)->orderByDesc('fecha_pago')->first();
    }
}
