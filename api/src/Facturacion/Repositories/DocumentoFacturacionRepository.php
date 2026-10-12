<?php

namespace App\Facturacion\Repositories;

use App\Core\Base\BaseRepository;
use App\Facturacion\Enums\EstadoDocumentoFacturacion;
use App\Facturacion\Enums\TipoDocumentoFacturacion;
use App\Facturacion\Models\DocumentoFacturacion;
use App\Facturacion\Models\LineaDocumentoFacturacion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class DocumentoFacturacionRepository extends BaseRepository
{
    public function __construct(DocumentoFacturacion $documento)
    {
        parent::__construct($documento);
    }

    /** @param list<TipoDocumentoFacturacion> $tipos */
    public function paginar(array $tipos, ?EstadoDocumentoFacturacion $estado, ?int $clienteId, int $porPagina, int $pagina): LengthAwarePaginator
    {
        $consulta = $this->consulta()
            ->with(['cliente.tercero', 'origen:id,numero,tipo'])
            ->orderByDesc('creado_en');

        $this->filtrar($consulta, $tipos, $estado, $clienteId);

        return $consulta->paginate(perPage: $porPagina, page: $pagina);
    }

    private function filtrar(Builder $consulta, array $tipos, ?EstadoDocumentoFacturacion $estado, ?int $clienteId): Builder
    {
        return $consulta
            ->whereIn('tipo', array_map(fn (TipoDocumentoFacturacion $tipo) => $tipo->value, $tipos))
            ->when($estado, fn (Builder $q) => $q->where('estado', $estado))
            ->when($clienteId, fn (Builder $q) => $q->where('cliente_id', $clienteId));
    }

    /** Bloquea la fila: dos notas simultáneas sobre la misma factura no pueden acreditar el mismo saldo dos veces. */
    public function bloquear(int $id): DocumentoFacturacion
    {
        return $this->consulta()->lockForUpdate()->findOrFail($id);
    }

    /** Explícito por empresa: lo usa el job de envío de correo, que no tiene sesión. */
    public function deEmpresa(int $id, int $empresaId): ?DocumentoFacturacion
    {
        return $this->consulta()->where('empresa_id', $empresaId)->whereKey($id)->first();
    }

    /** Igual que `bloquear()` pero para varias filas a la vez: un cobro que cruza varias facturas las bloquea todas en la misma transacción. */
    public function bloquearVarias(array $ids): Collection
    {
        return $this->consulta()->with('cliente.tercero')->whereIn('id', $ids)->lockForUpdate()->get();
    }

    /** Total del documento: suma de sus líneas activas, en centavos. */
    public function sumaLineas(int $documentoId): int
    {
        return (int) LineaDocumentoFacturacion::query()->where('documento_id', $documentoId)->sum('valor');
    }

    /** @return array{iva: int, retefuente: int, reteica: int, reteiva: int} centavos de impuesto de las líneas activas. */
    public function sumaImpuestos(int $documentoId): array
    {
        $fila = LineaDocumentoFacturacion::query()
            ->where('documento_id', $documentoId)
            ->selectRaw('SUM(valor_iva) as iva, SUM(valor_retefuente) as retefuente, SUM(valor_reteica) as reteica, SUM(valor_reteiva) as reteiva')
            ->first();

        return [
            'iva' => (int) ($fila->iva ?? 0),
            'retefuente' => (int) ($fila->retefuente ?? 0),
            'reteica' => (int) ($fila->reteica ?? 0),
            'reteiva' => (int) ($fila->reteiva ?? 0),
        ];
    }

    /**
     * Total de las notas de un tipo emitidas sobre una factura, en centavos. Va con la empresa explícita: la
     * consulta cruza documentos y líneas, y el scope de empresa solo filtra la tabla base.
     */
    public function sumaNotas(int $empresaId, int $facturaId, TipoDocumentoFacturacion $tipo): int
    {
        return (int) DB::table('lineas_documento_facturacion as l')
            ->join('documentos_facturacion as d', fn ($join) => $join->on('d.id', '=', 'l.documento_id')->on('d.empresa_id', '=', 'l.empresa_id'))
            ->where('l.empresa_id', $empresaId)
            ->where('d.documento_origen_id', $facturaId)
            ->where('d.tipo', $tipo->value)
            ->where('d.estado', EstadoDocumentoFacturacion::Emitido->value)
            ->whereNull('d.borrado_en')
            ->whereNull('l.borrado_en')
            ->sum('l.valor');
    }

    /** Facturas emitidas (no notas) de un crédito, para que el Service derive cuáles están anuladas antes de validar duplicados. */
    public function facturasEmitidasDelCredito(int $creditoId): Collection
    {
        return $this->consulta()
            ->where('credito_id', $creditoId)
            ->where('tipo', TipoDocumentoFacturacion::Factura)
            ->where('estado', EstadoDocumentoFacturacion::Emitido)
            ->get();
    }

    /** ¿Hay documentos emitidos de este tipo? Bloquea cambiar el número inicial de su secuencia. */
    public function existeEmitido(int $empresaId, TipoDocumentoFacturacion $tipo): bool
    {
        return $this->consulta()
            ->where('empresa_id', $empresaId)
            ->where('tipo', $tipo)
            ->where('estado', EstadoDocumentoFacturacion::Emitido)
            ->exists();
    }
}
