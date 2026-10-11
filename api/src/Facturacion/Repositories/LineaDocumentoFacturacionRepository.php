<?php

namespace App\Facturacion\Repositories;

use App\Core\Base\BaseRepository;
use App\Core\Dinero\Dinero;
use App\Facturacion\Enums\EstadoDocumentoFacturacion;
use App\Facturacion\Enums\TipoDocumentoFacturacion;
use App\Facturacion\Models\DocumentoFacturacion;
use App\Facturacion\Models\LineaDocumentoFacturacion;
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
                $activas[$indice]->update($datos);

                continue;
            }

            $this->crear([
                'empresa_id' => $documento->empresa_id,
                'documento_id' => $documento->id,
                'orden' => $siguienteOrden++,
                ...$datos,
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
                'concepto' => $datos['concepto'],
                'descripcion' => $datos['descripcion'],
                'valor' => $datos['valor'],
                'tasa_mora' => $datos['tasa_mora'] ?? null,
                'dias_mora' => $datos['dias_mora'] ?? null,
                'base_mora' => $datos['base_mora'] ?? null,
            ]);
        }
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
