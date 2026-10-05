<?php

namespace App\Importacion\Procesadores;

use App\Core\Dinero\Dinero;
use App\Creditos\Models\Cuota;
use App\Creditos\Repositories\CreditoRepository;
use App\Creditos\Repositories\CuotaRepository;
use App\Importacion\Data\ContextoEjecucion;
use App\Importacion\Data\FilaRechazada;
use App\Importacion\Lectura\ConvertidorValor;
use App\Importacion\Repositories\DatosImportacionRepository;

/**
 * Plan de cuotas del archivo: cada fila corrige una cuota del plan del crédito. Solo se acepta mientras el crédito no
 * tenga pagos; las cuotas se actualizan en sitio (nunca se borran) y el saldo de capital se recalcula desde el plan.
 */
final class ProcesadorPlanCuotas implements ProcesadorFila
{
    public function __construct(
        private readonly DatosImportacionRepository $datos,
        private readonly CuotaRepository $cuotas,
        private readonly CreditoRepository $creditos,
    ) {}

    public function precargar(array $valoresLote, ContextoEjecucion $ctx): void
    {
        $ctx->creditosPorCodigo = $this->datos->creditosPorCodigos(
            $ctx->empresaId,
            array_values(array_filter(array_column($valoresLote, 'codigo'))),
        );
    }

    public function procesar(array $valores, ContextoEjecucion $ctx): void
    {
        $codigo = $valores['codigo'];
        if ($codigo === '') {
            throw new FilaRechazada('El código de crédito es obligatorio.', 'codigo');
        }

        $numero = ConvertidorValor::entero($valores['numero'], 'numero')
            ?? throw new FilaRechazada('El número de cuota es obligatorio.', 'numero');
        $fecha = ConvertidorValor::fecha($valores['fecha'], 'fecha')
            ?? throw new FilaRechazada('La fecha de vencimiento es obligatoria.', 'fecha');
        $total = ConvertidorValor::monto($valores['total'], 'total')
            ?? throw new FilaRechazada('El valor total de la cuota es obligatorio.', 'total');
        $capital = ConvertidorValor::monto($valores['capital'], 'capital');
        $interes = ConvertidorValor::monto($valores['interes'], 'interes');

        if (! $total->esPositivo()) {
            throw new FilaRechazada('El valor total de la cuota debe ser mayor que cero.', 'total');
        }

        $creditoId = $ctx->creditosPorCodigo[$codigo]
            ?? throw new FilaRechazada("Crédito no importado: «{$codigo}». Importa primero la hoja Creditos.", 'codigo');

        if ($capital !== null && $interes !== null && ! $capital->sumar($interes)->igualA($total)) {
            throw new FilaRechazada('El valor capital más el interés no suman el valor total de la cuota.', 'total');
        }

        if (! $ctx->primeraVez("cuota|{$codigo}|{$numero}")) {
            throw new FilaRechazada('La cuota está repetida en el archivo.', 'numero');
        }

        $ctx->planes[$creditoId] ??= $this->datos->cuotasDelCredito($creditoId);

        if ($this->datos->creditoTienePagos($creditoId)) {
            throw new FilaRechazada('El crédito ya tiene pagos: su plan no se puede reemplazar.', 'codigo');
        }

        $cuota = $ctx->planes[$creditoId]->firstWhere('numero_cuota', $numero)
            ?? throw new FilaRechazada("El crédito no tiene la cuota {$numero} en su plan.", 'numero');

        if ($capital === null || $interes === null) {
            throw new FilaRechazada('Para reemplazar el plan indica el valor capital y el valor interés de la cuota.', 'capital');
        }

        if ($this->coincide($cuota, $fecha, $total, $capital, $interes)) {
            return;
        }

        if (! $ctx->escribir) {
            return;
        }

        $this->cuotas->actualizar($cuota, [
            'fecha_vencimiento' => $fecha,
            'valor' => $total,
            'valor_capital' => $capital,
            'valor_interes' => $interes,
        ]);

        $this->recalcularSaldos($creditoId);
        $ctx->planes[$creditoId] = $this->datos->cuotasDelCredito($creditoId);
    }

    private function coincide(Cuota $cuota, string $fecha, Dinero $total, Dinero $capital, Dinero $interes): bool
    {
        return $cuota->fecha_vencimiento->format('Y-m-d') === $fecha
            && $cuota->valor->igualA($total)
            && $cuota->valor_capital->igualA($capital)
            && $cuota->valor_interes->igualA($interes);
    }

    /** Sin pagos, el saldo de capital es el préstamo menos el capital acumulado del plan; el total del crédito sale de las cuotas. */
    private function recalcularSaldos(int $creditoId): void
    {
        $credito = $this->creditos->buscarPorIdOFallar($creditoId);
        $saldo = $credito->valor_prestamo;
        $totalPlan = Dinero::cero();

        foreach ($this->cuotas->todasOrdenadasParaActualizar($creditoId) as $cuota) {
            $saldo = $saldo->restar($cuota->valor_capital);
            $totalPlan = $totalPlan->sumar($cuota->valor);
            $this->cuotas->actualizar($cuota, ['saldo_capital' => $saldo->noNegativo()]);
        }

        $this->creditos->actualizar($credito, ['valor_total' => $totalPlan]);
    }
}
