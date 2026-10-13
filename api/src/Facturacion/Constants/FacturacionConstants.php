<?php

namespace App\Facturacion\Constants;

class FacturacionConstants
{
    /**
     * Listado y detalle de documentos: cada tipo exige su permiso de lectura (lo resuelve `AccesoDocumentosFacturacion`);
     * quien crea facturas o registra cobros también los consulta.
     */
    public const CONSULTA_DOCUMENTOS = [
        'facturacion.listar_facturas', 'facturacion.listar_notas_credito', 'facturacion.listar_notas_debito',
        'facturacion.crear', 'facturacion.registrar_cobro',
    ];
}
