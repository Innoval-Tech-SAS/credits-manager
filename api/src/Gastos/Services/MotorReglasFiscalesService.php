<?php

namespace App\Gastos\Services;

use App\Core\Dinero\Dinero;
use App\Gastos\Enums\TipoRetencion;
use App\Gastos\Models\ParametrizacionTipoGasto;
use App\Gastos\Models\TarifaReteIcaMunicipio;
use App\Gastos\Models\ValorUvt;
use App\Terceros\Enums\TipoPersona;
use App\Terceros\Models\Tercero;
use Illuminate\Validation\ValidationException;

/**
 * Evalúa ReteFuente, ReteICA y ReteIVA de forma independiente (RF-05): cada una se
 * incluye o excluye por su propia regla, sin afectar el cálculo de las demás, y cada
 * decisión queda trazada en el arreglo `retenciones` devuelto para que
 * `GastoService::causar()` la persista en `gasto_reglas_fiscales_aplicadas`.
 */
class MotorReglasFiscalesService
{
    public function evaluar(
        Dinero $valorBruto,
        ParametrizacionTipoGasto $parametrizacion,
        ?Tercero $tercero,
        ?TarifaReteIcaMunicipio $tarifaReteIca,
        ValorUvt $uvt,
    ): array {
        $valorIva = $parametrizacion->tarifa_iva
            ? $valorBruto->multiplicarPorTasa((float) $parametrizacion->tarifa_iva / 100)
            : Dinero::cero();

        $retenciones = [
            $this->evaluarRetefuente($valorBruto, $parametrizacion, $tercero, $uvt),
            $this->evaluarReteica($valorBruto, $parametrizacion, $tarifaReteIca),
            $this->evaluarReteiva($valorIva, $parametrizacion, $tercero),
        ];

        $totalRetenciones = Dinero::sumarVarios(...array_column($retenciones, 'valor'));

        return [
            'valor_iva' => $valorIva,
            'valor_neto' => $valorBruto->sumar($valorIva)->restar($totalRetenciones),
            'retenciones' => $retenciones,
        ];
    }

    private function evaluarRetefuente(Dinero $valorBruto, ParametrizacionTipoGasto $parametrizacion, ?Tercero $tercero, ValorUvt $uvt): array
    {
        $base = ['tipo' => TipoRetencion::ReteFuente, 'valor' => Dinero::cero(), 'tarifa_aplicada' => null];

        if (! $parametrizacion->aplica_retefuente) {
            return [...$base, 'incluida' => false, 'regla' => 'no_aplica_tipo_gasto', 'detalle' => 'El tipo de gasto no aplica ReteFuente.'];
        }

        if (! $tercero) {
            return [...$base, 'incluida' => false, 'regla' => 'sin_tercero', 'detalle' => 'El gasto no tiene un tercero asociado.'];
        }

        if ($tercero->autorretenedor) {
            return [...$base, 'incluida' => false, 'regla' => 'autorretenedor', 'detalle' => 'El tercero es autorretenedor.'];
        }

        $baseMinima = $uvt->valor->multiplicarPorTasa((float) ($parametrizacion->base_minima_uvt ?? 0));
        if ($parametrizacion->base_minima_uvt !== null && $valorBruto->menorQue($baseMinima)) {
            return [...$base, 'incluida' => false, 'regla' => 'bajo_base_minima', 'detalle' => "Valor bruto {$valorBruto->aDecimalString()} menor a la base mínima {$baseMinima->aDecimalString()}."];
        }

        $tarifa = $tercero->tipo_persona === TipoPersona::Juridica
            ? $parametrizacion->tarifa_retefuente_persona_juridica
            : $parametrizacion->tarifa_retefuente_persona_natural;

        if (! $tarifa) {
            return [...$base, 'incluida' => false, 'regla' => 'sin_tarifa_parametrizada', 'detalle' => 'No hay tarifa de ReteFuente parametrizada para el tipo de persona del tercero.'];
        }

        return [
            'tipo' => TipoRetencion::ReteFuente,
            'incluida' => true,
            'valor' => $valorBruto->multiplicarPorTasa((float) $tarifa / 100),
            'tarifa_aplicada' => (float) $tarifa,
            'regla' => 'tarifa_por_tipo_persona',
            'detalle' => "Tarifa aplicada según tipo de persona ({$tercero->tipo_persona->value}).",
        ];
    }

    private function evaluarReteica(Dinero $valorBruto, ParametrizacionTipoGasto $parametrizacion, ?TarifaReteIcaMunicipio $tarifaReteIca): array
    {
        $base = ['tipo' => TipoRetencion::ReteIca, 'valor' => Dinero::cero(), 'tarifa_aplicada' => null];

        if (! $parametrizacion->aplica_reteica) {
            return [...$base, 'incluida' => false, 'regla' => 'no_aplica_tipo_gasto', 'detalle' => 'El tipo de gasto no aplica ReteICA.'];
        }

        if (! $tarifaReteIca) {
            throw ValidationException::withMessages([
                'municipio_id' => __('validation.custom.tarifa_reteica.no_vigente'),
            ]);
        }

        return [
            'tipo' => TipoRetencion::ReteIca,
            'incluida' => true,
            'valor' => $valorBruto->multiplicarPorTasa((float) $tarifaReteIca->tarifa / 1000),
            'tarifa_aplicada' => (float) $tarifaReteIca->tarifa,
            'regla' => 'tarifa_por_municipio_actividad',
            'detalle' => 'Tarifa vigente por municipio y actividad económica del tercero.',
        ];
    }

    private function evaluarReteiva(Dinero $valorIva, ParametrizacionTipoGasto $parametrizacion, ?Tercero $tercero): array
    {
        $base = ['tipo' => TipoRetencion::ReteIva, 'valor' => Dinero::cero(), 'tarifa_aplicada' => null];

        if (! $parametrizacion->aplica_reteiva) {
            return [...$base, 'incluida' => false, 'regla' => 'no_aplica_tipo_gasto', 'detalle' => 'El tipo de gasto no aplica ReteIVA.'];
        }

        if (! $tercero) {
            return [...$base, 'incluida' => false, 'regla' => 'sin_tercero', 'detalle' => 'El gasto no tiene un tercero asociado.'];
        }

        if (! $tercero->responsable_iva) {
            return [...$base, 'incluida' => false, 'regla' => 'no_responsable_iva', 'detalle' => 'El tercero no es responsable de IVA.'];
        }

        if (! $valorIva->esPositivo()) {
            return [...$base, 'incluida' => false, 'regla' => 'sin_iva_generado', 'detalle' => 'El gasto no generó IVA.'];
        }

        $tarifa = (float) $parametrizacion->tarifa_reteiva;

        return [
            'tipo' => TipoRetencion::ReteIva,
            'incluida' => true,
            'valor' => $valorIva->multiplicarPorTasa($tarifa / 100),
            'tarifa_aplicada' => $tarifa,
            'regla' => 'tarifa_parametrizada',
            'detalle' => 'Tarifa de ReteIVA parametrizada para el tipo de gasto.',
        ];
    }
}
