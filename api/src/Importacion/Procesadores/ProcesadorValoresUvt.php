<?php

namespace App\Importacion\Procesadores;

use App\Causacion\Services\ValorUvtService;
use App\Importacion\Data\ContextoEjecucion;
use App\Importacion\Data\FilaRechazada;
use App\Importacion\Lectura\ConvertidorValor;

/** Un valor por año, propio de la empresa que importa. Reimportar un año existente lo reemplaza. */
final class ProcesadorValoresUvt implements ProcesadorFila
{
    public function __construct(private readonly ValorUvtService $valoresUvt) {}

    public function precargar(array $valoresLote, ContextoEjecucion $ctx): void {}

    public function procesar(array $valores, ContextoEjecucion $ctx): void
    {
        $anio = ConvertidorValor::entero($valores['anio'], 'anio')
            ?? throw new FilaRechazada('El año es obligatorio.', 'anio');

        if ($anio < 2000 || $anio > 2100) {
            throw new FilaRechazada('El año debe estar entre 2000 y 2100.', 'anio');
        }

        $valor = ConvertidorValor::monto($valores['valor'], 'valor')
            ?? throw new FilaRechazada('El valor UVT es obligatorio.', 'valor');

        if (! $valor->esPositivo()) {
            throw new FilaRechazada('El valor UVT debe ser mayor que cero.', 'valor');
        }

        if (! $ctx->escribir) {
            return;
        }

        $this->valoresUvt->guardar($anio, (float) $valor->aDecimalString());
    }
}
