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

    /**
     * Valores por defecto de los parámetros de cada empresa. `nota_debito_tope` en centavos
     * (1.000.000,00 COP): límite por defecto, configurable por empresa.
     */
    public const PARAMETROS_DEFECTO = [
        'nota_debito_tope' => 100_000_000,
    ];
}
