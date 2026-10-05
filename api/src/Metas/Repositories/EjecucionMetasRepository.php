<?php

namespace App\Metas\Repositories;

use App\Clientes\Models\ClienteAsesorHistorial;
use App\Creditos\Enums\EstadoCredito;
use App\Creditos\Models\Credito;
use App\Metas\Data\PeriodoMeta;
use App\Pagos\Models\Pago;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lecturas que alimentan el cumplimiento de metas. Suma la empresa entera: por eso quita el alcance del asesor
 * (`AlcanceAsesorScope`) en este único lugar, y siempre con `empresa_id` explícito. El asesor que consulte
 * recibe solo su fila; el ejecutado de los demás nunca sale de aquí hacia la respuesta.
 */
class EjecucionMetasRepository
{
    /** Créditos desembolsados en el período, sin cancelados. Los refinanciados llegan con `credito_origen_id`. */
    public function creditosDesembolsados(int $empresaId, PeriodoMeta $periodo): Collection
    {
        return Credito::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->whereNotNull('desembolsado_en')
            ->where('desembolsado_en', '>=', $periodo->inicio()->format('Y-m-d H:i:s'))
            ->where('desembolsado_en', '<', $periodo->finExclusivo()->format('Y-m-d H:i:s'))
            ->where('estado', '!=', EstadoCredito::Cancelado->value)
            ->get(['id', 'cliente_id', 'valor_prestamo', 'saldo_refinanciado', 'credito_origen_id', 'desembolsado_en']);
    }

    public function pagosDelPeriodo(int $empresaId, PeriodoMeta $periodo): Collection
    {
        return Pago::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->where('fecha_pago', '>=', $periodo->inicio()->format('Y-m-d H:i:s'))
            ->where('fecha_pago', '<', $periodo->finExclusivo()->format('Y-m-d H:i:s'))
            ->get(['id', 'cliente_id', 'valor_pago', 'valor_capital', 'valor_interes', 'fecha_pago']);
    }

    /** Asignaciones de los clientes dados, en orden cronológico: de ahí sale el asesor vigente en cada fecha. */
    public function historialDeClientes(int $empresaId, array $clienteIds): Collection
    {
        return ClienteAsesorHistorial::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('cliente_id', $clienteIds)
            ->orderBy('creado_en')
            ->orderBy('id')
            ->get(['cliente_id', 'asesor_id', 'creado_en']);
    }
}
