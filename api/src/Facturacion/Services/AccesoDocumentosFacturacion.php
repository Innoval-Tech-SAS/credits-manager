<?php

namespace App\Facturacion\Services;

use App\Facturacion\Enums\TipoDocumentoFacturacion;
use App\Facturacion\Models\DocumentoFacturacion;
use App\Usuarios\Models\Usuario;

/**
 * Facturas, notas crédito y notas débito comparten endpoint, pero cada tipo tiene su permiso de lectura
 * (`facturacion.listar_facturas`, `listar_notas_credito`, `listar_notas_debito`). Además, quien crea facturas
 * consulta los documentos pendientes del cliente y quien registra cobros consulta las facturas a cobrar.
 */
class AccesoDocumentosFacturacion
{
    private const PERMISO_LECTURA = [
        'factura' => 'listar_facturas',
        'nota_credito' => 'listar_notas_credito',
        'nota_debito' => 'listar_notas_debito',
    ];

    public function puedeVer(Usuario $usuario, TipoDocumentoFacturacion $tipo): bool
    {
        return $usuario->tienePermiso('facturacion', self::PERMISO_LECTURA[$tipo->value])
            || $usuario->tienePermiso('facturacion', 'crear')
            || ($tipo === TipoDocumentoFacturacion::Factura && $usuario->tienePermiso('facturacion', 'registrar_cobro'));
    }

    /**
     * Tipos que el listado puede devolver: el pedido, si el usuario lo puede ver, o todos los que puede ver.
     *
     * @return list<TipoDocumentoFacturacion>
     */
    public function tiposVisibles(Usuario $usuario, ?TipoDocumentoFacturacion $pedido): array
    {
        $candidatos = $pedido !== null ? [$pedido] : TipoDocumentoFacturacion::cases();
        $visibles = array_values(array_filter($candidatos, fn (TipoDocumentoFacturacion $tipo) => $this->puedeVer($usuario, $tipo)));

        abort_if($visibles === [], 403, 'No tienes permiso para realizar esta acción.');

        return $visibles;
    }

    public function exigirVer(Usuario $usuario, DocumentoFacturacion $documento): void
    {
        abort_unless($this->puedeVer($usuario, $documento->tipo), 403, 'No tienes permiso para realizar esta acción.');
    }
}
