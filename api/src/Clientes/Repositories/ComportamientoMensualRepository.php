<?php

namespace App\Clientes\Repositories;

use App\Core\Base\BaseRepository;
use App\Creditos\Enums\EstadoCuota;
use App\Creditos\Models\Credito;
use App\Creditos\Models\Cuota;
use App\Pagos\Models\Pago;
use App\Pagos\Models\ReversaPago;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Agregados mensuales del cliente. Cada consulta se arma desde el modelo Eloquent, así que hereda `EmpresaScope` y
 * `AlcanceAsesorScope`; las subconsultas correlacionadas solo miran filas que esos scopes ya dejaron pasar.
 * Los montos salen en centavos y los meses como `YYYY-MM` (hora de Bogotá, la que guarda la aplicación).
 */
class ComportamientoMensualRepository extends BaseRepository
{
    public function __construct(Pago $pago)
    {
        parent::__construct($pago);
    }

    /** Recaudo por mes de pago. Un pago reversado en su mismo mes o antes no cuenta; una reversa posterior no lo borra de su mes. */
    public function recaudoPorMes(int $clienteId): Collection
    {
        return $this->consulta()
            ->where('cliente_id', $clienteId)
            ->whereDoesntHave('reversa', fn (Builder $reversa) => $reversa->whereRaw('SUBSTR(reversas_pago.fecha, 1, 7) <= SUBSTR(pagos.fecha_pago, 1, 7)'))
            ->selectRaw('SUBSTR(pagos.fecha_pago, 1, 7) as mes, COALESCE(SUM(pagos.valor_pago), 0) as recaudado')
            ->groupBy('mes')
            ->get();
    }

    /**
     * Cuotas que hoy están pagadas, por mes de cumplimiento. La fecha de cumplimiento es la del último pago aplicado
     * a la cuota, porque es el que la completa. A tiempo: cumplida el mismo día del vencimiento o antes; con mora: después.
     */
    public function cuotasPagadasPorMes(int $clienteId): Collection
    {
        $pagadas = Cuota::query()
            ->whereHas('credito', fn (Builder $credito) => $credito->where('cliente_id', $clienteId))
            ->where('estado', EstadoCuota::Pagada)
            ->selectRaw('cuotas.fecha_vencimiento, (
                SELECT MAX(pagos.fecha_pago)
                FROM pago_detalle
                INNER JOIN pagos ON pagos.id = pago_detalle.pago_id
                WHERE pago_detalle.cuota_id = cuotas.id
                  AND pago_detalle.borrado_en IS NULL
                  AND pagos.borrado_en IS NULL
                  AND NOT EXISTS (SELECT 1 FROM reversas_pago WHERE reversas_pago.pago_id = pagos.id)
            ) as cumplida_en');

        return DB::query()
            ->fromSub($pagadas, 'cuotas_pagadas')
            ->whereNotNull('cumplida_en')
            ->selectRaw('SUBSTR(cumplida_en, 1, 7) as mes,
                SUM(CASE WHEN DATE(cumplida_en) <= fecha_vencimiento THEN 1 ELSE 0 END) as a_tiempo,
                SUM(CASE WHEN DATE(cumplida_en) > fecha_vencimiento THEN 1 ELSE 0 END) as con_mora')
            ->groupBy('mes')
            ->get();
    }

    /**
     * Movimientos que cambian el saldo, por mes, ya acumulados: desembolso (suma el exigible de sus cuotas), pago
     * (resta lo aplicado a cuotas) y reversa (devuelve lo aplicado en el mes en que ocurre). Cada fila trae el saldo
     * al cierre de su mes, sumando todos los meses anteriores, aunque caigan fuera de la ventana de 12 meses.
     */
    public function saldoAcumuladoPorMes(int $clienteId): Collection
    {
        $desembolsos = Credito::query()
            ->where('cliente_id', $clienteId)
            ->selectRaw('SUBSTR(COALESCE(creditos.desembolsado_en, creditos.fecha_desembolso), 1, 7) as mes,
                (SELECT COALESCE(SUM(cuotas.valor + cuotas.valor_otros_gastos), 0) FROM cuotas WHERE cuotas.credito_id = creditos.id AND cuotas.borrado_en IS NULL) as delta');

        $aplicados = Pago::query()
            ->where('cliente_id', $clienteId)
            ->selectRaw('SUBSTR(pagos.fecha_pago, 1, 7) as mes,
                -(SELECT COALESCE(SUM(pago_detalle.valor_aplicado), 0) FROM pago_detalle WHERE pago_detalle.pago_id = pagos.id AND pago_detalle.borrado_en IS NULL) as delta');

        $reversados = ReversaPago::query()
            ->whereHas('pago', fn (Builder $pago) => $pago->where('cliente_id', $clienteId))
            ->selectRaw('SUBSTR(reversas_pago.fecha, 1, 7) as mes,
                (SELECT COALESCE(SUM(pago_detalle.valor_aplicado), 0) FROM pago_detalle WHERE pago_detalle.pago_id = reversas_pago.pago_id AND pago_detalle.borrado_en IS NULL) as delta');

        $movimientos = $desembolsos->toBase()
            ->unionAll($aplicados->toBase())
            ->unionAll($reversados->toBase());

        $porMes = DB::query()
            ->fromSub($movimientos, 'movimientos')
            ->selectRaw('mes, SUM(delta) as delta')
            ->groupBy('mes');

        return DB::query()
            ->fromSub($porMes, 'por_mes')
            ->selectRaw('mes, SUM(delta) OVER (ORDER BY mes) as saldo')
            ->orderBy('mes')
            ->get();
    }
}
