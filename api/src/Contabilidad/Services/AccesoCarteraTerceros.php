<?php

namespace App\Contabilidad\Services;

use App\Contabilidad\Enums\NaturalezaDocumento;
use App\Usuarios\Models\Usuario;

/**
 * Cuentas por pagar y por cobrar son dos pantallas sobre el mismo endpoint (`naturaleza`): cada lado exige su permiso
 * de lectura. Los anticipos se leen con el lado que luego cruzan, con `anticipos.listar` (consulta de anticipos) o con
 * `anticipos.crear`, que los necesita para cruzarlos. Cruzar también consulta los documentos pendientes del otro lado,
 * pero solo los del tercero elegido: `anticipos.crear` no abre la cartera completa.
 */
class AccesoCarteraTerceros
{
    public function puede(Usuario $usuario, NaturalezaDocumento $naturaleza, ?int $terceroId = null): bool
    {
        return match ($naturaleza) {
            NaturalezaDocumento::PorPagar => $usuario->tienePermiso('contabilidad', 'listar_cuentas_por_pagar')
                || ($terceroId !== null && $usuario->tienePermiso('anticipos', 'crear')),
            NaturalezaDocumento::PorCobrar => $usuario->tienePermiso('contabilidad', 'listar_cuentas_por_cobrar')
                || ($terceroId !== null && $usuario->tienePermiso('anticipos', 'crear')),
            NaturalezaDocumento::AnticipoProveedor => $usuario->tienePermiso('contabilidad', 'listar_cuentas_por_pagar')
                || $this->puedeConsultarAnticipos($usuario),
            NaturalezaDocumento::AnticipoCliente => $usuario->tienePermiso('contabilidad', 'listar_cuentas_por_cobrar')
                || $this->puedeConsultarAnticipos($usuario),
        };
    }

    public function exigir(Usuario $usuario, NaturalezaDocumento $naturaleza, ?int $terceroId = null): void
    {
        abort_unless($this->puede($usuario, $naturaleza, $terceroId), 403, 'No tienes permiso para realizar esta acción.');
    }

    private function puedeConsultarAnticipos(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('anticipos', 'listar') || $usuario->tienePermiso('anticipos', 'crear');
    }
}
