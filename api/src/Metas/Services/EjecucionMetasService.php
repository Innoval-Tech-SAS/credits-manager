<?php

namespace App\Metas\Services;

use App\Core\Dinero\Dinero;
use App\Creditos\Models\Credito;
use App\Metas\Constants\MetasConstants;
use App\Metas\Data\ParametrosMetas;
use App\Metas\Data\PeriodoMeta;
use App\Metas\Enums\BaseRecaudo;
use App\Metas\Enums\ModoColocacionRefinanciaciones;
use App\Metas\Enums\TipoMeta;
use App\Metas\Repositories\EjecucionMetasRepository;
use App\Pagos\Models\Pago;
use Carbon\CarbonImmutable;

/**
 * Suma lo ejecutado de un período y lo atribuye al asesor vigente en la fecha de cada hecho, según el historial
 * de asignaciones del cliente (no el asesor actual). Recibe `empresaId` explícito: también lo usan los comandos.
 */
class EjecucionMetasService
{
    private const TAMANIO_LOTE_CLIENTES = 500;

    public function __construct(protected EjecucionMetasRepository $ejecucion) {}

    /** @return array{total: Dinero, porAsesor: array<int, Dinero>} */
    public function calcular(int $empresaId, PeriodoMeta $periodo, TipoMeta $tipo, ParametrosMetas $parametros): array
    {
        $eventos = $tipo === TipoMeta::Colocacion
            ? $this->eventosDeColocacion($empresaId, $periodo, $parametros)
            : $this->eventosDeRecaudo($empresaId, $periodo, $parametros);

        $historial = $this->historialPorCliente($empresaId, array_values(array_unique(array_column($eventos, 'cliente_id'))));

        $total = Dinero::cero();
        $porAsesor = [];

        foreach ($eventos as $evento) {
            $total = $total->sumar($evento['valor']);

            $asesorId = $this->asesorEn($historial[$evento['cliente_id']] ?? [], $evento['momento']);

            if ($asesorId !== null) {
                $porAsesor[$asesorId] = ($porAsesor[$asesorId] ?? Dinero::cero())->sumar($evento['valor']);
            }
        }

        return ['total' => $total, 'porAsesor' => $porAsesor];
    }

    /** @return list<array{cliente_id: int, momento: CarbonImmutable, valor: Dinero}> */
    private function eventosDeColocacion(int $empresaId, PeriodoMeta $periodo, ParametrosMetas $parametros): array
    {
        $eventos = [];

        foreach ($this->ejecucion->creditosDesembolsados($empresaId, $periodo) as $credito) {
            $valor = $this->colocacionDelCredito($credito, $parametros->modoColocacionRefinanciaciones);

            if ($valor->esCero()) {
                continue;
            }

            $eventos[] = [
                'cliente_id' => $credito->cliente_id,
                'momento' => CarbonImmutable::instance($credito->desembolsado_en),
                'valor' => $valor,
            ];
        }

        return $eventos;
    }

    /** @return list<array{cliente_id: int, momento: CarbonImmutable, valor: Dinero}> */
    private function eventosDeRecaudo(int $empresaId, PeriodoMeta $periodo, ParametrosMetas $parametros): array
    {
        $eventos = [];

        foreach ($this->ejecucion->pagosDelPeriodo($empresaId, $periodo) as $pago) {
            $valor = $this->recaudoDelPago($pago, $parametros->baseRecaudo);

            if ($valor->esCero()) {
                continue;
            }

            $eventos[] = [
                'cliente_id' => $pago->cliente_id,
                'momento' => CarbonImmutable::parse($pago->getRawOriginal('fecha_pago'), MetasConstants::ZONA_HORARIA),
                'valor' => $valor,
            ];
        }

        return $eventos;
    }

    /** Un refinanciamiento solo aporta el dinero nuevo: el saldo tomado ya se colocó en el crédito anterior. */
    private function colocacionDelCredito(Credito $credito, ModoColocacionRefinanciaciones $modo): Dinero
    {
        if ($credito->credito_origen_id === null) {
            return $credito->valor_prestamo;
        }

        return match ($modo) {
            ModoColocacionRefinanciaciones::Excluir => Dinero::cero(),
            ModoColocacionRefinanciaciones::SoloDineroNuevo => $credito->valor_prestamo->restar($credito->saldo_refinanciado ?? Dinero::cero())->noNegativo(),
            ModoColocacionRefinanciaciones::Total => $credito->valor_prestamo,
        };
    }

    /** Pagos anteriores a la migración de desglose no tienen capital ni interés: cuentan cero en esas bases, no se adivinan. */
    private function recaudoDelPago(Pago $pago, BaseRecaudo $base): Dinero
    {
        return match ($base) {
            BaseRecaudo::Total => $pago->valor_pago,
            BaseRecaudo::SoloCapital => $pago->valor_capital ?? Dinero::cero(),
            BaseRecaudo::SoloInteres => $pago->valor_interes ?? Dinero::cero(),
        };
    }

    /** @return array<int, list<array{0: CarbonImmutable, 1: int}>> cliente_id => [momento, asesor_id] en orden */
    private function historialPorCliente(int $empresaId, array $clienteIds): array
    {
        $porCliente = [];

        foreach (array_chunk($clienteIds, self::TAMANIO_LOTE_CLIENTES) as $lote) {
            foreach ($this->ejecucion->historialDeClientes($empresaId, $lote) as $fila) {
                $porCliente[$fila->cliente_id][] = [CarbonImmutable::parse($fila->creado_en, MetasConstants::ZONA_HORARIA), (int) $fila->asesor_id];
            }
        }

        return $porCliente;
    }

    /** Asesor vigente en `$momento`; antes de la primera asignación registrada, el de la primera. */
    private function asesorEn(array $filas, CarbonImmutable $momento): ?int
    {
        $asesorId = null;

        foreach ($filas as [$desde, $asesor]) {
            if ($desde > $momento) {
                break;
            }

            $asesorId = $asesor;
        }

        return $asesorId ?? ($filas[0][1] ?? null);
    }
}
