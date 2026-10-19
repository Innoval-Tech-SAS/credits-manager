<?php

namespace App\Importacion\Procesadores;

use App\Contabilidad\Repositories\SaldoAperturaRepository;
use App\Core\Dinero\Dinero;
use App\Importacion\Data\ContextoEjecucion;
use App\Importacion\Data\FilaRechazada;
use App\Importacion\Lectura\ConvertidorValor;
use App\Importacion\Repositories\DatosImportacionRepository;

/**
 * Saldos de apertura por cuenta PUC y fecha de corte. Re-subir el mismo saldo se rechaza como duplicado. Si la
 * importación genera asiento, cada fila aceptada se acumula en `$ctx->lineasApertura` — `ImportacionEjecutorService`
 * arma con ellas un único comprobante de apertura al terminar todo el archivo, no fila por fila.
 */
final class ProcesadorSaldosIniciales implements ProcesadorFila
{
    public function __construct(
        private readonly DatosImportacionRepository $datos,
        private readonly SaldoAperturaRepository $saldos,
    ) {}

    public function precargar(array $valoresLote, ContextoEjecucion $ctx): void
    {
        $codigosCuenta = array_values(array_filter(array_column($valoresLote, 'cuenta')));

        $ctx->cuentasPorCodigo = $this->datos->cuentasPorCodigos($ctx->empresaId, $codigosCuenta);
        $ctx->cuentasConDatosPorCodigo = $this->datos->cuentasConDatosPorCodigos($ctx->empresaId, $codigosCuenta);
        $ctx->primerAsientoPorCuenta = $this->datos->primerAsientoPorCuenta($ctx->empresaId, array_values($ctx->cuentasPorCodigo));

        $cedulas = array_values(array_filter(array_column($valoresLote, 'cedula')));
        $ctx->clientesPorCedula = $this->datos->clientesPorCedulas($ctx->empresaId, $cedulas);
        $ctx->tercerosPorClienteId = $this->datos->tercerosPorClientes($ctx->empresaId, array_values($ctx->clientesPorCedula));
    }

    public function procesar(array $valores, ContextoEjecucion $ctx): void
    {
        $fecha = ConvertidorValor::fecha($valores['fecha_corte'], 'fecha_corte')
            ?? throw new FilaRechazada('La fecha de corte es obligatoria.', 'fecha_corte');

        if ($valores['cuenta'] === '') {
            throw new FilaRechazada('La cuenta PUC es obligatoria.', 'cuenta');
        }

        $debito = ConvertidorValor::monto($valores['debito'], 'debito') ?? Dinero::cero();
        $credito = ConvertidorValor::monto($valores['credito'], 'credito') ?? Dinero::cero();

        if (! $debito->esPositivo() && ! $credito->esPositivo()) {
            throw new FilaRechazada('Indica el saldo débito o el saldo crédito.', 'debito');
        }

        $cuentaId = $ctx->cuentasPorCodigo[$valores['cuenta']]
            ?? throw new FilaRechazada("Cuenta no encontrada: {$valores['cuenta']}.", 'cuenta');

        if (! $ctx->primeraVez("saldo|{$cuentaId}|{$fecha}")) {
            throw new FilaRechazada('El saldo de esta cuenta y fecha de corte está repetido en el archivo.', 'fecha_corte');
        }

        if ($this->datos->saldoExiste($ctx->empresaId, $cuentaId, $fecha)) {
            throw new FilaRechazada('Ya existe un saldo para esta cuenta y fecha de corte.', 'fecha_corte');
        }

        $primerAsiento = $ctx->primerAsientoPorCuenta[$cuentaId] ?? null;

        if ($primerAsiento !== null && $primerAsiento <= $fecha) {
            throw new FilaRechazada("La cuenta {$valores['cuenta']} ya tiene asientos desde el {$primerAsiento}: este saldo los contaría dos veces. Usa una fecha de corte anterior o quita la cuenta del archivo.", 'fecha_corte');
        }

        $cuenta = $ctx->cuentasConDatosPorCodigo[$valores['cuenta']] ?? null;
        $terceroId = null;

        if ($valores['cedula'] !== '') {
            if ($cuenta === null || ! $cuenta->esCarteraDeTerceros()) {
                throw new FilaRechazada("La cuenta {$valores['cuenta']} no es de cartera: no lleva cédula.", 'cedula');
            }

            $clienteId = $ctx->clientesPorCedula[$valores['cedula']]
                ?? throw new FilaRechazada("No existe un cliente con cédula {$valores['cedula']}.", 'cedula');

            $terceroId = $ctx->tercerosPorClienteId[$clienteId] ?? null;
        }

        if (! $ctx->escribir) {
            return;
        }

        $this->saldos->crear([
            'cuenta_id' => $cuentaId,
            'tercero_id' => $terceroId,
            'fecha_corte' => $fecha,
            'debito' => $debito,
            'credito' => $credito,
        ]);

        $ctx->lineasApertura[] = [
            'cuenta_id' => $cuentaId,
            'tercero_id' => $terceroId,
            'debito' => $debito,
            'credito' => $credito,
            'fecha_corte' => $fecha,
        ];
    }
}
