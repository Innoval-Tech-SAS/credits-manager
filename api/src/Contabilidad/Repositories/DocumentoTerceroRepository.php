<?php

namespace App\Contabilidad\Repositories;

use App\Contabilidad\Enums\EstadoDocumentoTercero;
use App\Contabilidad\Enums\NaturalezaDocumento;
use App\Contabilidad\Models\DocumentoTercero;
use App\Core\Base\BaseRepository;
use App\Terceros\Models\Tercero;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class DocumentoTerceroRepository extends BaseRepository
{
    public function __construct(DocumentoTercero $documentoTercero)
    {
        parent::__construct($documentoTercero);
    }

    /** @var list<EstadoDocumentoTercero> Estados con saldo pendiente de aplicar (todavía admiten un abono). */
    private const ESTADOS_CON_SALDO = [EstadoDocumentoTercero::Pendiente, EstadoDocumentoTercero::AbonadoParcial];

    public function pendientesPorTercero(Tercero $tercero, NaturalezaDocumento $naturaleza): Collection
    {
        return $this->consulta()
            ->where('tercero_id', $tercero->id)
            ->where('naturaleza', $naturaleza)
            ->whereIn('estado', self::ESTADOS_CON_SALDO)
            ->orderBy('fecha')
            ->get();
    }

    /**
     * Bloquea (`lockForUpdate`) y devuelve los documentos con saldo de `$tercero`
     * cuyo id esté en `$ids`. Se usa dentro de una transacción para validar que la
     * selección del usuario sigue siendo válida (mismo tercero, misma naturaleza,
     * todavía con saldo) justo antes de aplicarles un valor.
     */
    public function bloquearPendientesPorIds(array $ids, int $terceroId, NaturalezaDocumento $naturaleza): Collection
    {
        return $this->consulta()
            ->whereIn('id', $ids)
            ->where('tercero_id', $terceroId)
            ->where('naturaleza', $naturaleza)
            ->whereIn('estado', self::ESTADOS_CON_SALDO)
            ->lockForUpdate()
            ->get();
    }

    /** Bloquea un documento por id sin filtrar por estado: una nota débito o la reversa de un cobro/egreso puede
     *  aumentar el saldo incluso de un documento ya cancelado (vuelve a quedar con saldo). */
    public function bloquearPorId(int $id): DocumentoTercero
    {
        return $this->consulta()->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    /** Lectura simple (sin lock) de varios documentos por id, para armar las líneas de un comprobante antes de cruzarlos. */
    public function porIds(array $ids): Collection
    {
        return $this->consulta()->whereIn('id', $ids)->get();
    }

    /** Busca el documento de un tercero por su número (factura/documento cruce), sin importar su estado. */
    public function buscarPorNumero(int $terceroId, NaturalezaDocumento $naturaleza, string $numeroDocumento): ?DocumentoTercero
    {
        return $this->consulta()
            ->where('tercero_id', $terceroId)
            ->where('naturaleza', $naturaleza)
            ->where('numero_documento', $numeroDocumento)
            ->with('tercero')
            ->first();
    }

    /** Documentos que un origen concreto (p. ej. un comprobante manual) creó, para poder deshacerlos si se reversa. */
    public function creadosPorOrigen(string $moduloOrigen, int $origenId): Collection
    {
        return $this->consulta()
            ->where('modulo_origen', $moduloOrigen)
            ->where('origen_id', $origenId)
            ->get();
    }

    /**
     * Cartera por tercero (CxP o CxC): `$filtros['estado']` filtra por el estado que se le muestra al usuario,
     * donde "vencido" es un derivado de `saldo > 0` y `fecha_vencimiento` anterior a la fecha de corte — no un
     * valor de la columna `estado`, que solo guarda pendiente/abonado_parcial/cancelado.
     *
     * @param  array{naturaleza: NaturalezaDocumento, tercero_id: ?int, desde: ?string, hasta: ?string, estado: ?string, plan_cuenta_id: ?int, fecha_corte: string}  $filtros
     */
    public function listarConFiltros(array $filtros, int $porPagina, int $pagina): LengthAwarePaginator
    {
        return $this->consultaFiltrada($filtros)
            ->with(['tercero:id,nombre,tipo_documento,numero_documento', 'planCuenta:id,codigo,nombre'])
            ->orderBy('fecha_vencimiento')
            ->orderBy('fecha')
            ->paginate(perPage: $porPagina, page: $pagina);
    }

    /** Documentos vivos (saldo > 0) de la naturaleza y tercero dados, para agrupar por edad en el Service. */
    public function conSaldoParaEdades(NaturalezaDocumento $naturaleza, ?int $terceroId): Collection
    {
        return $this->consulta()
            ->select('id', 'saldo', 'fecha_vencimiento')
            ->where('naturaleza', $naturaleza)
            ->whereIn('estado', self::ESTADOS_CON_SALDO)
            ->when($terceroId !== null, fn ($query) => $query->where('tercero_id', $terceroId))
            ->get();
    }

    /** @param  array{naturaleza: NaturalezaDocumento, tercero_id: ?int, desde: ?string, hasta: ?string, estado: ?string, plan_cuenta_id: ?int, fecha_corte: string}  $filtros */
    private function consultaFiltrada(array $filtros): Builder
    {
        $consulta = $this->consulta()
            ->where('naturaleza', $filtros['naturaleza'])
            ->when($filtros['tercero_id'] !== null, fn ($query) => $query->where('tercero_id', $filtros['tercero_id']))
            ->when($filtros['desde'] !== null, fn ($query) => $query->whereDate('fecha', '>=', $filtros['desde']))
            ->when($filtros['hasta'] !== null, fn ($query) => $query->whereDate('fecha', '<=', $filtros['hasta']))
            ->when($filtros['plan_cuenta_id'] !== null, fn ($query) => $query->where('plan_cuenta_id', $filtros['plan_cuenta_id']));

        $vencido = fn ($query) => $query
            ->whereIn('estado', self::ESTADOS_CON_SALDO)
            ->whereNotNull('fecha_vencimiento')
            ->whereDate('fecha_vencimiento', '<', $filtros['fecha_corte']);

        $noVencido = fn ($query) => $query
            ->where(fn ($sub) => $sub->whereNull('fecha_vencimiento')->orWhereDate('fecha_vencimiento', '>=', $filtros['fecha_corte']));

        match ($filtros['estado']) {
            'vencido' => $consulta->where($vencido),
            'pendiente' => $consulta->where('estado', EstadoDocumentoTercero::Pendiente)->where($noVencido),
            'abonado_parcial' => $consulta->where('estado', EstadoDocumentoTercero::AbonadoParcial)->where($noVencido),
            'cancelado' => $consulta->where('estado', EstadoDocumentoTercero::Cancelado),
            'anulado' => $consulta->where('estado', EstadoDocumentoTercero::Anulado),
            default => null,
        };

        return $consulta;
    }
}
