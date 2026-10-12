<?php

namespace App\Contabilidad\Services;

use App\Contabilidad\Constants\ContabilidadConstants;
use App\Contabilidad\Repositories\PlanCuentaRepository;
use App\Usuarios\Models\Usuario;

/**
 * Libro mayor y Caja y bancos son dos pantallas sobre el mismo informe: con `listar_libro_mayor` se consulta
 * cualquier cuenta; con `listar_caja_bancos`, solo las cuentas de caja y bancos (clase 11 del PUC).
 */
class AccesoLibroMayor
{
    public function __construct(protected PlanCuentaRepository $cuentas) {}

    public function exigir(Usuario $usuario, int $cuentaId): void
    {
        if ($usuario->tienePermiso('informes_contables', 'listar_libro_mayor')) {
            return;
        }

        $codigo = $this->cuentas->buscarPorId($cuentaId)?->codigo;
        $esCajaBancos = $codigo !== null && str_starts_with($codigo, ContabilidadConstants::PREFIJO_CUENTAS_CAJA_BANCOS);

        abort_unless(
            $esCajaBancos && $usuario->tienePermiso('informes_contables', 'listar_caja_bancos'),
            403,
            'No tienes permiso para realizar esta acción.',
        );
    }
}
