<?php

namespace App\Contabilidad\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Services\EmisorAuditoria;
use App\Contabilidad\Constants\ContabilidadConstants;
use App\Contabilidad\Models\PlanCuenta;
use App\Contabilidad\Repositories\PlanCuentaRepository;
use BackedEnum;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class PlanCuentaService
{
    public function __construct(
        protected PlanCuentaRepository $cuentas,
        protected EmisorAuditoria $auditoria,
    ) {}

    public function listar(?string $busqueda = null): Collection
    {
        return $this->cuentas->todasOrdenadas($busqueda);
    }

    public function crear(array $datos): PlanCuenta
    {
        $cuenta = $this->cuentas->crear($datos);

        $this->auditoria->registrarCreacion(AuditoriaConstants::MODULO_CONTABILIDAD, "Cuenta {$cuenta->codigo} {$cuenta->nombre} creada", $cuenta);

        return $cuenta;
    }

    public function editar(PlanCuenta $cuenta, array $datos): PlanCuenta
    {
        $this->validarCuentaPadre($cuenta, isset($datos['cuenta_padre_id']) ? (int) $datos['cuenta_padre_id'] : null);
        $this->validarCambiosEstructurales($cuenta, $datos);

        $anteriores = $this->auditoria->instantanea($cuenta);
        $cuenta = $this->cuentas->actualizar($cuenta, $datos);

        $this->auditoria->registrarModificacion(AuditoriaConstants::MODULO_CONTABILIDAD, "Cuenta {$cuenta->codigo} modificada", $cuenta, $anteriores);

        return $cuenta;
    }

    /** El padre no puede ser la cuenta ni una de sus descendientes: se sube desde el padre propuesto buscando la cuenta. */
    private function validarCuentaPadre(PlanCuenta $cuenta, ?int $cuentaPadreId): void
    {
        if ($cuentaPadreId === null) {
            return;
        }

        if ($cuentaPadreId === $cuenta->id) {
            throw ValidationException::withMessages([
                'cuenta_padre_id' => __('validation.custom.plan_cuenta.padre_es_la_misma_cuenta'),
            ]);
        }

        $padres = $this->cuentas->mapaDePadres();
        $visitadas = [];

        for ($actual = $cuentaPadreId; $actual !== null && ! isset($visitadas[$actual]); $actual = isset($padres[$actual]) ? (int) $padres[$actual] : null) {
            if ($actual === $cuenta->id) {
                throw ValidationException::withMessages([
                    'cuenta_padre_id' => __('validation.custom.plan_cuenta.padre_es_descendiente'),
                ]);
            }

            $visitadas[$actual] = true;
        }
    }

    private function validarCambiosEstructurales(PlanCuenta $cuenta, array $datos): void
    {
        $cambiados = array_values(array_filter(
            ContabilidadConstants::CAMPOS_ESTRUCTURALES_CUENTA,
            fn (string $campo) => array_key_exists($campo, $datos) && self::valorComparable($cuenta->{$campo}) !== (string) $datos[$campo],
        ));

        if ($cambiados === [] || ! $this->cuentas->tieneMovimientos($cuenta->id)) {
            return;
        }

        throw ValidationException::withMessages(
            array_fill_keys($cambiados, __('validation.custom.plan_cuenta.cambio_estructural_con_movimientos')),
        );
    }

    private static function valorComparable(mixed $valor): string
    {
        return $valor instanceof BackedEnum ? (string) $valor->value : (string) $valor;
    }
}
