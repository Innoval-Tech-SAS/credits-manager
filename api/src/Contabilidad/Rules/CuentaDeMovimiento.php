<?php

namespace App\Contabilidad\Rules;

use App\Contabilidad\Models\PlanCuenta;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Cuenta de la empresa que puede recibir asientos: de detalle y activa. Una cuenta mayor no suma en el balance de
 * prueba ni en los estados financieros, así que un asiento sobre ella descuadra los reportes contra el libro diario.
 */
class CuentaDeMovimiento implements ValidationRule
{
    public function __construct(private readonly int $empresaId) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $cuenta = is_numeric($value)
            ? PlanCuenta::query()->where('empresa_id', $this->empresaId)->find((int) $value, ['id', 'codigo', 'permite_movimiento', 'activa'])
            : null;

        if (! $cuenta) {
            $fail('validation.exists')->translate();
        } elseif (! $cuenta->permite_movimiento) {
            $fail('validation.custom.plan_cuenta.no_admite_movimiento')->translate(['cuenta' => $cuenta->codigo]);
        } elseif (! $cuenta->activa) {
            $fail('validation.custom.plan_cuenta.inactiva')->translate(['cuenta' => $cuenta->codigo]);
        }
    }
}
