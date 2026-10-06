<?php

namespace App\Importacion\Procesadores;

use App\Core\Dinero\Dinero;
use App\Creditos\Models\Cuota;
use App\Creditos\Repositories\CreditoRepository;
use App\Importacion\Data\ContextoEjecucion;
use App\Importacion\Data\FilaRechazada;
use App\Importacion\Lectura\ConvertidorValor;
use App\Importacion\Repositories\DatosImportacionRepository;
use App\Pagos\Services\AplicadorPagoPlan;
use App\Pagos\Services\PagoService;
use Illuminate\Support\Collection;

/**
 * Pagos históricos. Un pago solo se acepta si cuadra con el plan: se aplica a la cuota pendiente más antigua, no excede
 * el saldo y su tipo coincide con el valor. Nada se ajusta en silencio ni se descarta un sobrante.
 */
final class ProcesadorPagos implements ProcesadorFila
{
    private const TIPO_CUOTA_COMPLETA = 'CUOTA COMPLETA';

    private const TIPO_ABONO_PARCIAL = 'ABONO PARCIAL';

    public function __construct(
        private readonly DatosImportacionRepository $datos,
        private readonly CreditoRepository $creditos,
        private readonly PagoService $pagos,
    ) {}

    public function precargar(array $valoresLote, ContextoEjecucion $ctx): void
    {
        $ctx->creditosPorCodigo = $this->datos->creditosPorCodigos(
            $ctx->empresaId,
            array_values(array_filter(array_column($valoresLote, 'codigo'))),
        );

        if ($ctx->usuariosPorNombre === []) {
            $ctx->usuariosPorNombre = $this->datos->usuariosPorNombre($ctx->empresaId);
        }
    }

    public function procesar(array $valores, ContextoEjecucion $ctx): void
    {
        $codigo = $valores['codigo'];
        if ($codigo === '') {
            throw new FilaRechazada('El código de crédito es obligatorio.', 'codigo');
        }

        $fecha = ConvertidorValor::fecha($valores['fecha'], 'fecha')
            ?? throw new FilaRechazada('La fecha de pago es obligatoria.', 'fecha');
        $valor = ConvertidorValor::monto($valores['valor'], 'valor')
            ?? throw new FilaRechazada('El valor pagado es obligatorio.', 'valor');
        $numero = ConvertidorValor::entero($valores['numero'], 'numero');

        if (! $valor->esPositivo()) {
            throw new FilaRechazada('El valor pagado debe ser mayor que cero.', 'valor');
        }

        $tipo = $this->tipo($valores['tipo']);

        $creditoId = $ctx->creditosPorCodigo[$codigo]
            ?? throw new FilaRechazada("Crédito no importado: «{$codigo}». Importa primero la hoja Creditos.", 'codigo');

        $pendientes = $this->pendientes($creditoId, $ctx);

        if ($pendientes->isEmpty()) {
            throw new FilaRechazada('El crédito no tiene cuotas pendientes.', 'codigo');
        }

        $saldoTotal = $pendientes->reduce(fn (Dinero $acumulado, Cuota $cuota) => $acumulado->sumar($cuota->valor->restar($cuota->valor_pagado)), Dinero::cero());

        if ($valor->mayorQue($saldoTotal)) {
            throw new FilaRechazada("El valor supera el saldo pendiente del crédito «{$codigo}» ({$saldoTotal->aDecimalString()}).", 'valor');
        }

        /** @var Cuota $objetivo */
        $objetivo = $pendientes->first();
        $saldoObjetivo = $objetivo->valor->restar($objetivo->valor_pagado);

        if ($numero !== null && $numero !== (int) $objetivo->numero_cuota) {
            throw new FilaRechazada("El pago no cuadra con el plan: se aplica a la cuota {$objetivo->numero_cuota}, no a la {$numero}.", 'numero');
        }

        if ($tipo === self::TIPO_CUOTA_COMPLETA && ! $valor->igualA($saldoObjetivo)) {
            throw new FilaRechazada("El valor no coincide con el saldo de la cuota {$objetivo->numero_cuota} ({$saldoObjetivo->aDecimalString()}). Si es un abono, usa Abono parcial.", 'tipo');
        }

        if ($tipo === self::TIPO_ABONO_PARCIAL && $valor->mayorOIgualQue($saldoObjetivo)) {
            throw new FilaRechazada("El valor cubre la cuota {$objetivo->numero_cuota}: usa Cuota completa.", 'tipo');
        }

        $registradoPor = $valores['registrado_por'] !== ''
            ? ($ctx->usuariosPorNombre[ConvertidorValor::nombreNormalizado($valores['registrado_por'])] ?? null)
            : null;

        if (! $ctx->escribir) {
            $this->simularAplicacion($pendientes, $valor);

            return;
        }

        $this->pagos->registrarPagoHistorico(
            $this->creditos->buscarPorIdOFallar($creditoId),
            $valor,
            $fecha,
            [
                'tipo' => $valores['tipo'],
                'cuenta' => $valores['cuenta'] !== '' ? $valores['cuenta'] : null,
                'registrado_por' => $valores['registrado_por'] !== '' ? $valores['registrado_por'] : null,
                'observaciones' => $valores['observaciones'] !== '' ? $valores['observaciones'] : null,
            ],
            $registradoPor,
            $ctx->generarAsiento,
        );
    }

    /**
     * Validación: el plan vive en memoria y cada pago aceptado lo avanza, así el siguiente pago del archivo se evalúa
     * contra el saldo que tendrá. Escritura: se lee el plan actual de la base en cada fila.
     *
     * @return Collection<int, Cuota>
     */
    private function pendientes(int $creditoId, ContextoEjecucion $ctx): Collection
    {
        $cuotas = $ctx->escribir
            ? $this->datos->cuotasDelCredito($creditoId)
            : ($ctx->planes[$creditoId] ??= $this->datos->cuotasDelCredito($creditoId));

        return $cuotas
            ->filter(fn (Cuota $cuota) => $cuota->valor->restar($cuota->valor_pagado)->esPositivo())
            ->values();
    }

    /** @param Collection<int, Cuota> $pendientes */
    private function simularAplicacion(Collection $pendientes, Dinero $valor): void
    {
        $aplicacion = AplicadorPagoPlan::aplicar(
            $pendientes->map(fn (Cuota $cuota) => [
                'valor' => $cuota->valor->sumar($cuota->valor_otros_gastos),
                'valor_pagado' => $cuota->valor_pagado,
                'valor_interes' => $cuota->valor_interes,
                'valor_otros_gastos' => $cuota->valor_otros_gastos,
            ])->all(),
            $valor,
        );

        foreach ($aplicacion['asignaciones'] as $asignacion) {
            $pendientes[$asignacion['posicion']]->valor_pagado = $asignacion['nuevo_pagado'];
        }
    }

    private function tipo(string $valor): string
    {
        $tipo = ConvertidorValor::nombreNormalizado($valor);

        if (! in_array($tipo, [self::TIPO_CUOTA_COMPLETA, self::TIPO_ABONO_PARCIAL], true)) {
            throw new FilaRechazada("Tipo no reconocido: «{$valor}». Usa Cuota completa o Abono parcial.", 'tipo');
        }

        return $tipo;
    }
}
