<?php

namespace App\Pagos\Repositories;

use App\Core\Base\BaseRepository;
use App\Pagos\Models\Pago;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

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

        return $this->filtrar($consulta, $busqueda)->paginate(perPage: $porPagina, page: $pagina);
    }

    /** Mismo listado que la pantalla, con empresa y asesor explícitos, para exportar. */
    public function consultaParaExportar(int $empresaId, ?int $asesorId, ?string $busqueda): Builder
    {
        $consulta = $this->consulta()
            ->where('pagos.empresa_id', $empresaId)
            ->with(['cliente.tercero', 'recibo:id,pago_id,numero,estado'])
            ->orderByDesc('fecha_pago');

        if ($asesorId !== null) {
            $consulta->whereIn('pagos.cliente_id', DB::table('clientes')->select('id')->where('empresa_id', $empresaId)->where('asesor_id', $asesorId));
        }

        return $this->filtrar($consulta, $busqueda);
    }

    public function cursorParaExportar(int $empresaId, ?int $asesorId, ?string $busqueda): LazyCollection
    {
        return $this->consultaParaExportar($empresaId, $asesorId, $busqueda)->lazy();
    }

    public function contarParaExportar(int $empresaId, ?int $asesorId, ?string $busqueda): int
    {
        return $this->consultaParaExportar($empresaId, $asesorId, $busqueda)->reorder()->count();
    }

    private function filtrar(Builder $consulta, ?string $busqueda): Builder
    {
        if ($busqueda) {
            $consulta->whereHas('cliente.tercero', fn ($q) => $q->where('nombre', 'like', "%{$busqueda}%"));
        }

        return $consulta;
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
