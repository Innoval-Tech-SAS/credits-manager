<?php

namespace App\Contabilidad\Services;

use App\Contabilidad\Enums\EstadoDocumentoTercero;
use App\Contabilidad\Enums\NaturalezaDocumento;
use App\Contabilidad\Models\DocumentoTercero;
use App\Contabilidad\Repositories\DocumentoTerceroRepository;
use App\Core\Dinero\Dinero;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * Consulta de cartera por tercero (CxP o CxC), alimentada por `documentos_tercero`: el mismo motor que ya cruzan
 * Gastos (por_pagar) y Facturación (por_cobrar). No captura nada nuevo, solo lee y agrupa lo que esos módulos ya
 * escriben, tal como pide DDA ("una consulta... no una captura manual").
 */
class CarteraTerceroService
{
    public function __construct(protected DocumentoTerceroRepository $documentos) {}

    /**
     * @param  array{naturaleza: NaturalezaDocumento, tercero_id: ?int, desde: ?string, hasta: ?string, estado: ?string, plan_cuenta_id: ?int, fecha_corte: ?string}  $filtros
     */
    public function listar(array $filtros, int $porPagina, int $pagina): LengthAwarePaginator
    {
        $filtros['fecha_corte'] ??= Carbon::now('America/Bogota')->toDateString();

        return $this->documentos
            ->listarConFiltros($filtros, $porPagina, $pagina)
            ->through(fn (DocumentoTercero $documento) => $this->presentar($documento, $filtros['fecha_corte']));
    }

    /** Cartera por edades: corriente, 1-30, 31-60, 61-90 y más de 90 días de mora a la fecha de corte. */
    public function edades(NaturalezaDocumento $naturaleza, ?int $terceroId, ?string $fechaCorte): array
    {
        $fechaCorte ??= Carbon::now('America/Bogota')->toDateString();
        $corte = Carbon::parse($fechaCorte);

        $buckets = [
            'corriente' => Dinero::cero(),
            '1_30' => Dinero::cero(),
            '31_60' => Dinero::cero(),
            '61_90' => Dinero::cero(),
            'mas_90' => Dinero::cero(),
        ];

        foreach ($this->documentos->conSaldoParaEdades($naturaleza, $terceroId) as $documento) {
            $dias = $this->diasDesdeVencimiento($documento->fecha_vencimiento, $corte) ?? 0;
            $buckets[$this->bucket($dias)] = $buckets[$this->bucket($dias)]->sumar($documento->saldo);
        }

        return $buckets;
    }

    /** Positivo si `$fechaVencimiento` ya pasó respecto a `$corte` (en mora); negativo si todavía falta. */
    private function diasDesdeVencimiento(?Carbon $fechaVencimiento, Carbon $corte): ?int
    {
        if ($fechaVencimiento === null) {
            return null;
        }

        return $fechaVencimiento->copy()->startOfDay()->diffInDays($corte->copy()->startOfDay());
    }

    private function bucket(int $diasDeMora): string
    {
        return match (true) {
            $diasDeMora <= 0 => 'corriente',
            $diasDeMora <= 30 => '1_30',
            $diasDeMora <= 60 => '31_60',
            $diasDeMora <= 90 => '61_90',
            default => 'mas_90',
        };
    }

    private function presentar(DocumentoTercero $documento, string $fechaCorte): array
    {
        $corte = Carbon::parse($fechaCorte);
        $diasDesdeVencimiento = $this->diasDesdeVencimiento($documento->fecha_vencimiento, $corte);
        $vencido = $documento->estado !== EstadoDocumentoTercero::Cancelado && $diasDesdeVencimiento !== null && $diasDesdeVencimiento > 0;

        return [
            'id' => $documento->id,
            'tipo_documento' => $documento->tipo_documento,
            'numero_documento' => $documento->numero_documento,
            'fecha' => $documento->fecha->toDateString(),
            'fecha_vencimiento' => $documento->fecha_vencimiento?->toDateString(),
            'dias_mora' => $vencido ? $diasDesdeVencimiento : 0,
            'dias_por_vencer' => ! $vencido && $diasDesdeVencimiento !== null ? max(0, -$diasDesdeVencimiento) : null,
            'concepto' => $documento->concepto,
            'valor_original' => $documento->valor_original,
            // Si una nota débito dejó el saldo por encima del valor original, no hay abono negativo que mostrar.
            'abonado' => $documento->valor_original->restar($documento->saldo)->noNegativo(),
            'saldo' => $documento->saldo,
            'estado' => $vencido ? 'vencido' : $documento->estado->value,
            'tercero' => $documento->tercero,
            'plan_cuenta' => $documento->planCuenta,
            // Para que el frontend pueda enlazar al documento de origen (p. ej. la factura en Facturación).
            'modulo_origen' => $documento->modulo_origen,
            'origen_id' => $documento->origen_id,
        ];
    }
}
