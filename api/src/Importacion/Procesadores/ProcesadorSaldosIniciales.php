<?php

namespace App\Importacion\Procesadores;

use App\Contabilidad\Repositories\SaldoAperturaRepository;
use App\Core\Dinero\Dinero;
use App\Importacion\Data\ContextoEjecucion;
use App\Importacion\Data\FilaRechazada;
use App\Importacion\Lectura\ConvertidorValor;
use App\Importacion\Repositories\DatosImportacionRepository;

/** Saldos de apertura por cuenta PUC y fecha de corte. Re-subir el mismo saldo se rechaza como duplicado. */
final class ProcesadorSaldosIniciales implements ProcesadorFila
{
    public function __construct(
        private readonly DatosImportacionRepository $datos,
        private readonly SaldoAperturaRepository $saldos,
    ) {}

    public function precargar(array $valoresLote, ContextoEjecucion $ctx): void
    {
        $ctx->cuentasPorCodigo = $this->datos->cuentasPorCodigos(
            $ctx->empresaId,
            array_values(array_filter(array_column($valoresLote, 'cuenta'))),
        );
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

        if (! $ctx->escribir) {
            return;
        }

        $this->saldos->crear([
            'cuenta_id' => $cuentaId,
            'fecha_corte' => $fecha,
            'debito' => $debito,
            'credito' => $credito,
        ]);
    }
}
