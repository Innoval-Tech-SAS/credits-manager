<?php

namespace App\Contabilidad\Services;

use App\Contabilidad\Enums\NaturalezaDocumento;
use App\Usuarios\Models\Usuario;

/**
 * Cuentas por pagar y por cobrar son dos pantallas sobre el mismo endpoint (`naturaleza`): cada lado exige su
 * permiso de lectura. Un anticipo se lee con el lado que luego cruza. Cruzar anticipos consulta ambos lados.
 */
class AccesoCarteraTerceros
{
    public function exigir(Usuario $usuario, NaturalezaDocumento $naturaleza): void
    {
        $accion = match ($naturaleza) {
            NaturalezaDocumento::PorPagar, NaturalezaDocumento::AnticipoProveedor => 'listar_cuentas_por_pagar',
            NaturalezaDocumento::PorCobrar, NaturalezaDocumento::AnticipoCliente => 'listar_cuentas_por_cobrar',
        };

        abort_unless(
            $usuario->tienePermiso('contabilidad', $accion) || $usuario->tienePermiso('anticipos', 'crear'),
            403,
            'No tienes permiso para realizar esta acción.',
        );
    }
}
