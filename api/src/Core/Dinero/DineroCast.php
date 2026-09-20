<?php

namespace App\Core\Dinero;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * `get()` siempre devuelve una instancia `Dinero` a partir del entero de BD (centavos).
 * `set()` acepta tanto `Dinero` como int/float/string decimal — esto último por
 * conveniencia en fixtures de tests y en código que aún recibe montos como valor
 * crudo desde un FormRequest validado con la regla `numeric`.
 * Implementa `SerializesCastableAttributes` para que `$modelo->toArray()` (sin pasar
 * por `json_encode`) también devuelva el string decimal, no el objeto `Dinero` crudo.
 */
class DineroCast implements CastsAttributes, SerializesCastableAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Dinero
    {
        return $value === null ? null : Dinero::desdeCentavos((int) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        if ($value === null) {
            return null;
        }

        return ($value instanceof Dinero ? $value : Dinero::desdePesos($value))->centavos();
    }

    public function serialize(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value?->aDecimalString();
    }
}
