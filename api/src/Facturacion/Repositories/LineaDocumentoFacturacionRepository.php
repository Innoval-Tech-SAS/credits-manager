<?php

namespace App\Facturacion\Repositories;

use App\Core\Base\BaseRepository;
use App\Core\Dinero\Dinero;
use App\Facturacion\Enums\EstadoDocumentoFacturacion;
use App\Facturacion\Enums\TipoConceptoFacturacion;
use App\Facturacion\Enums\TipoDocumentoFacturacion;
use App\Facturacion\Models\DocumentoFacturacion;
use App\Facturacion\Models\LineaDocumentoFacturacion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LineaDocumentoFacturacionRepository extends BaseRepository
{
    public function __construct(LineaDocumentoFacturacion $linea)
    {
        parent::__construct($linea);
    }

    /**
     * Reemplaza las líneas de un borrador sin borrar filas: cada posición reutiliza su línea activa y el orden nunca
     * se reutiliza (el índice único por documento y orden incluye las borradas). Las sobrantes se marcan como borradas.
     *
     * @param  list<array{concepto: string, descripcion: string, valor: Dinero}>  $lineas
     */
    public function sincronizar(DocumentoFacturacion $documento, array $lineas): void
    {
        $todas = $this->consulta()->withTrashed()->where('documento_id', $documento->id)->orderBy('orden')->get();
        $activas = $todas->whereNull('borrado_en')->values();
        $siguienteOrden = ($todas->max('orden') ?? 0) + 1;

        foreach ($lineas as $indice => $datos) {
            if ($activas->has($indice)) {
                $activas[$indice]->update($this->datosDeLinea($datos));

                continue;
            }

            $this->crear([
                'empresa_id' => $documento->empresa_id,
                'documento_id' => $documento->id,
                'orden' => $siguienteOrden++,
                ...$this->datosDeLinea($datos),
            ]);
        }

        foreach ($activas->slice(count($lineas)) as $sobrante) {
            $sobrante->delete();
        }
    }

    /** Líneas de un documento recién creado: el orden es su posición, sin historial previo. */
    public function crearParaDocumento(DocumentoFacturacion $documento, array $lineas): void
    {
        foreach (array_values($lineas) as $indice => $datos) {
            $this->crear([
                'empresa_id' => $documento->empresa_id,
                'documento_id' => $documento->id,
                'linea_origen_id' => $datos['linea_origen_id'] ?? null,
                'orden' => $indice + 1,
                ...$this->datosDeLinea($datos),
            ]);
        }
    }

    /** Campos comunes de una línea, con los de impuestos/cuota en 0/null cuando no aplican (notas). */
    private function datosDeLinea(array $datos): array
    {
        return [
            'concepto' => $datos['concepto'],
            'descripcion' => $datos['descripcion'],
            'valor' => $datos['valor'],
            'cuota_id' => $datos['cuota_id'] ?? null,
            'tipo_concepto' => $datos['tipo_concepto'] ?? null,
            'valor_iva' => $datos['valor_iva'] ?? Dinero::cero(),
            'valor_retefuente' => $datos['valor_retefuente'] ?? Dinero::cero(),
            'valor_reteica' => $datos['valor_reteica'] ?? Dinero::cero(),
            'valor_reteiva' => $datos['valor_reteiva'] ?? Dinero::cero(),
            'tasa_mora' => $datos['tasa_mora'] ?? null,
            'dias_mora' => $datos['dias_mora'] ?? null,
            'base_mora' => $datos['base_mora'] ?? null,
        ];
    }

    /**
     * Cuota/concepto de líneas de facturas emitidas (no borradas) de este crédito, con el id del documento: una
     * factura anulada por nota crédito sigue "emitida" (el estado no se guarda, ver `EstadoDocumentoFacturacion`),
     * así que quien llama decide si la descuenta — `DocumentoFacturacionService::combosVigentes()` lo hace.
     *
     * @return Collection<int, object{documento_id: int, cuota_id: int, tipo_concepto: string}>
     */
    public function combosFacturados(int $empresaId, int $creditoId): Collection
    {
        return DB::table('lineas_documento_facturacion as l')
            ->join('documentos_facturacion as d', fn ($join) => $join->on('d.id', '=', 'l.documento_id')->on('d.empresa_id', '=', 'l.empresa_id'))
            ->where('l.empresa_id', $empresaId)
            ->where('d.credito_id', $creditoId)
            ->where('d.tipo', TipoDocumentoFacturacion::Factura->value)
            ->where('d.estado', EstadoDocumentoFacturacion::Emitido->value)
            ->whereNull('d.borrado_en')
            ->whereNull('l.borrado_en')
            ->whereNotNull('l.cuota_id')
            ->whereNotNull('l.tipo_concepto')
            ->get(['d.id as documento_id', 'l.cuota_id', 'l.tipo_concepto']);
    }

    /**
     * Duplicado de una línea manual (sin `cuota_id`): mismo crédito, mismo tipo de concepto, mismo texto de
     * concepto, en una factura emitida (no anulada) cuya fecha cae en el mismo mes/año que `$fecha` — no hay otro
     * campo de "período" disponible para una línea libre (8.2, ver `DocumentoFacturacionService::prepararLineas`).
     */
    public function conceptoManualYaFacturado(int $empresaId, int $creditoId, TipoConceptoFacturacion $tipoConcepto, string $concepto, string $fecha): bool
    {
        $inicioMes = Carbon::parse($fecha)->startOfMonth()->toDateString();
        $finMes = Carbon::parse($fecha)->endOfMonth()->toDateString();

        return DB::table('lineas_documento_facturacion as l')
            ->join('documentos_facturacion as d', fn ($join) => $join->on('d.id', '=', 'l.documento_id')->on('d.empresa_id', '=', 'l.empresa_id'))
            ->where('l.empresa_id', $empresaId)
            ->where('d.credito_id', $creditoId)
            ->where('d.tipo', TipoDocumentoFacturacion::Factura->value)
            ->where('d.estado', EstadoDocumentoFacturacion::Emitido->value)
            ->whereNull('d.borrado_en')
            ->whereNull('l.borrado_en')
            ->whereNull('l.cuota_id')
            ->where('l.tipo_concepto', $tipoConcepto->value)
            ->where('l.concepto', $concepto)
            ->whereBetween('d.fecha', [$inicioMes, $finMes])
            ->exists();
    }

    /** Centavos ya acreditados sobre una línea de factura por notas crédito emitidas. Va con la empresa explícita. */
    public function acreditado(int $empresaId, int $lineaOrigenId): int
    {
        return (int) DB::table('lineas_documento_facturacion as l')
            ->join('documentos_facturacion as d', fn ($join) => $join->on('d.id', '=', 'l.documento_id')->on('d.empresa_id', '=', 'l.empresa_id'))
            ->where('l.empresa_id', $empresaId)
            ->where('l.linea_origen_id', $lineaOrigenId)
            ->where('d.tipo', TipoDocumentoFacturacion::NotaCredito->value)
            ->where('d.estado', EstadoDocumentoFacturacion::Emitido->value)
            ->whereNull('d.borrado_en')
            ->whereNull('l.borrado_en')
            ->sum('l.valor');
    }
}
