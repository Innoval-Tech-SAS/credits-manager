<?php

namespace App\Contabilidad\Services;

use App\Contabilidad\Constants\ContabilidadConstants;
use App\Contabilidad\Enums\NaturalezaCuenta;
use App\Contabilidad\Repositories\AsientoContableRepository;
use App\Contabilidad\Repositories\DocumentoTerceroRepository;
use App\Contabilidad\Repositories\PlanCuentaRepository;
use App\Core\Dinero\Dinero;

/**
 * Controles de cierre mensual (6.6.3): reportes de solo lectura que comparan dos fuentes de datos independientes y
 * listan diferencias — no bloquean nada, igual que `ConciliacionAperturaService`. "Documentos huérfanos" (el
 * documento original dice "pagos que no apuntan a ninguna factura") se interpreta aquí como documentos con estado
 * inconsistente con su saldo: la arquitectura ya impide aplicar un valor a un documento que no existe (FK +
 * validación en `DocumentoTerceroService::aplicarValor`), así que un huérfano literal no es posible; lo que sí
 * puede pasar, y vale la pena detectar, es que `saldo` y `estado` queden desalineados entre sí. El sub-punto de
 * "retenciones (cuenta 2365) vs formulario 350 y certificados" queda fuera de alcance: el sistema no captura ese
 * formulario ni esos certificados hoy, no hay con qué comparar.
 */
class ControlesCierreService
{
    private const EPOCA = '2000-01-01';

    private const MODULOS_EGRESOS_RECIBOS = ['egresos', 'facturacion_cobro'];

    public function __construct(
        protected AuxiliarContableService $auxiliarContable,
        protected PlanCuentaRepository $cuentas,
        protected DocumentoTerceroRepository $documentos,
        protected AsientoContableRepository $asientos,
    ) {}

    /** @return array{cuentas_por_cobrar: array, cuentas_por_pagar: array, saldos_contrarios: array, documentos_inconsistentes: array, conciliacion_bancaria: array} */
    public function ejecutar(int $empresaId, string $hasta): array
    {
        return [
            'cuentas_por_cobrar' => $this->auxiliarVsBalance($empresaId, $hasta, ContabilidadConstants::PREFIJOS_CUENTAS_POR_COBRAR),
            'cuentas_por_pagar' => $this->auxiliarVsBalance($empresaId, $hasta, ContabilidadConstants::PREFIJOS_CUENTAS_POR_PAGAR),
            'saldos_contrarios' => $this->saldosContrarios($empresaId, $hasta),
            'documentos_inconsistentes' => $this->documentosInconsistentes($empresaId),
            'conciliacion_bancaria' => $this->conciliacionBancaria($empresaId, $hasta),
        ];
    }

    /**
     * Compara el total del auxiliar (`AuxiliarContableService`, que sale de `AsientoContableRepository`) contra el
     * total del balance de prueba (`PlanCuentaRepository::saldosParaBalancePrueba`) para las cuentas del prefijo
     * dado — son dos caminos de cálculo independientes sobre los mismos asientos; si difieren, hay un bug en uno
     * de los dos o un asiento mal clasificado.
     *
     * @param  list<string>  $prefijos
     */
    private function auxiliarVsBalance(int $empresaId, string $hasta, array $prefijos): array
    {
        $auxiliar = $this->auxiliarContable->auxiliar($empresaId, self::EPOCA, $hasta);
        $balance = $this->cuentas->saldosParaBalancePrueba($empresaId, $hasta);

        $totalAuxiliar = Dinero::sumarVarios(...collect($auxiliar['grupos'])
            ->filter(fn (array $grupo) => $this->coincidePrefijo($grupo['cuenta']['codigo'], $prefijos))
            ->pluck('saldo_final')
            ->all());

        $totalBalance = Dinero::sumarVarios(...$balance
            ->filter(fn ($fila) => $this->coincidePrefijo($fila->codigo, $prefijos))
            ->map(fn ($fila) => $this->saldoNaturalDeFila($fila))
            ->all());

        $diferencia = $totalAuxiliar->restar($totalBalance);

        return [
            'total_auxiliar' => $totalAuxiliar,
            'total_balance' => $totalBalance,
            'diferencia' => $diferencia,
            'cuadra' => $diferencia->esCero(),
        ];
    }

    /** Cuentas CxC (1305) con saldo crédito, o CxP (2205/2335) con saldo débito: error de cruce o anticipo mal clasificado. */
    private function saldosContrarios(int $empresaId, string $hasta): array
    {
        $filas = $this->cuentas->saldosParaBalancePrueba($empresaId, $hasta)
            ->filter(function ($fila) {
                $saldo = $this->saldoNaturalDeFila($fila);
                $esCxc = $this->coincidePrefijo($fila->codigo, ContabilidadConstants::PREFIJOS_CUENTAS_POR_COBRAR);
                $esCxp = $this->coincidePrefijo($fila->codigo, ContabilidadConstants::PREFIJOS_CUENTAS_POR_PAGAR);

                return ($esCxc && $saldo->esNegativo()) || ($esCxp && $saldo->esPositivo());
            })
            ->map(fn ($fila) => ['codigo' => $fila->codigo, 'nombre' => $fila->nombre, 'saldo' => $this->saldoNaturalDeFila($fila)])
            ->values();

        return ['filas' => $filas->all(), 'contador' => $filas->count()];
    }

    private function documentosInconsistentes(int $empresaId): array
    {
        $documentos = $this->documentos->inconsistentesEstadoSaldo($empresaId)->values();

        return ['filas' => $documentos->all(), 'contador' => $documentos->count()];
    }

    /** Egresos/recibos contabilizados sobre caja/bancos sin una marca de conciliación vigente en el rango. */
    private function conciliacionBancaria(int $empresaId, string $hasta): array
    {
        $asientos = $this->asientos->sinConciliarPorModuloOrigen(
            $empresaId,
            [ContabilidadConstants::PREFIJO_CUENTAS_CAJA_BANCOS],
            self::MODULOS_EGRESOS_RECIBOS,
            self::EPOCA,
            $hasta,
        )->values();

        return ['filas' => $asientos->all(), 'contador' => $asientos->count()];
    }

    /** @param list<string> $prefijos */
    private function coincidePrefijo(string $codigo, array $prefijos): bool
    {
        foreach ($prefijos as $prefijo) {
            if (str_starts_with($codigo, $prefijo)) {
                return true;
            }
        }

        return false;
    }

    private function saldoNaturalDeFila(object $fila): Dinero
    {
        // `saldosParaBalancePrueba()` hidrata instancias de PlanCuenta: `naturaleza` ya llega casteada al enum.
        return $this->saldoNatural(
            $fila->naturaleza,
            Dinero::desdeCentavos((int) $fila->debitos),
            Dinero::desdeCentavos((int) $fila->creditos),
        );
    }

    private function saldoNatural(NaturalezaCuenta $naturaleza, Dinero $debitos, Dinero $creditos): Dinero
    {
        return $naturaleza === NaturalezaCuenta::Debito ? $debitos->restar($creditos) : $creditos->restar($debitos);
    }
}
