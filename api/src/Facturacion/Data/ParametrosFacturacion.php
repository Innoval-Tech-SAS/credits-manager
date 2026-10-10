<?php

namespace App\Facturacion\Data;

use App\Core\Dinero\Dinero;
use App\Facturacion\Constants\FacturacionConstants;

/** Parámetros tipados de una empresa. Lo guardado es texto (`facturacion_configuracion.valor`); aquí se lee una sola vez. */
final readonly class ParametrosFacturacion
{
    public function __construct(
        public Dinero $notaDebitoTope,
    ) {}

    /** @param array<string, string> $guardados valores crudos de `facturacion_configuracion`; lo que falte toma el defecto */
    public static function desde(array $guardados): self
    {
        $crudo = $guardados['nota_debito_tope'] ?? null;

        return new self(
            notaDebitoTope: Dinero::desdeCentavos($crudo !== null ? (int) $crudo : FacturacionConstants::PARAMETROS_DEFECTO['nota_debito_tope']),
        );
    }

    /** @return array<string, Dinero> */
    public function aArray(): array
    {
        return ['nota_debito_tope' => $this->notaDebitoTope];
    }

    /** @return array<string, string> forma en que se guarda cada parámetro */
    public function aFilas(): array
    {
        return ['nota_debito_tope' => (string) $this->notaDebitoTope->centavos()];
    }
}
