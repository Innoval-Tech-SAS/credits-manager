<?php

namespace App\Recibos\Repositories;

use App\Core\Base\BaseRepository;
use App\Recibos\Enums\EstadoRecibo;
use App\Recibos\Models\ReciboCaja;
use Illuminate\Pagination\LengthAwarePaginator;

class ReciboCajaRepository extends BaseRepository
{
    public function __construct(ReciboCaja $recibo)
    {
        parent::__construct($recibo);
    }

    public function porPago(int $pagoId): ?ReciboCaja
    {
        return $this->consulta()->where('pago_id', $pagoId)->first();
    }

    /**
     * Recibos de la empresa filtrados por tercero, rango de fecha del pago y estado. El `whereHas('pago')` hereda el
     * alcance por asesor del pago, así que un asesor solo ve los recibos de sus clientes.
     *
     * @param  array{tercero_id: ?int, desde: ?string, hasta: ?string, estado: ?EstadoRecibo}  $filtros
     */
    public function paginarFiltrado(array $filtros, int $porPagina, int $pagina): LengthAwarePaginator
    {
        $consulta = $this->consulta()
            ->with(['pago.cliente.tercero', 'pago.credito', 'anulador:id,nombre'])
            ->whereHas('pago', function ($pago) use ($filtros) {
                if ($filtros['tercero_id'] !== null) {
                    $pago->whereHas('cliente', fn ($cliente) => $cliente->where('tercero_id', $filtros['tercero_id']));
                }

                if ($filtros['desde'] !== null) {
                    $pago->whereDate('fecha_pago', '>=', $filtros['desde']);
                }

                if ($filtros['hasta'] !== null) {
                    $pago->whereDate('fecha_pago', '<=', $filtros['hasta']);
                }
            })
            ->orderByDesc('consecutivo');

        if ($filtros['estado'] !== null) {
            $consulta->where('estado', $filtros['estado']->value);
        }

        return $consulta->paginate(perPage: $porPagina, page: $pagina);
    }
}
