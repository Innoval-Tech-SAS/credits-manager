/**
 * El detalle sirve a facturas y notas: el backend exige el permiso de lectura del tipo del documento, o crear
 * facturas / registrar cobros (que consultan los documentos del cliente). La ruta deja entrar con cualquiera.
 */
export const INVOICE_DETAIL_PERMISSIONS = [
    'facturacion.listar_facturas',
    'facturacion.listar_notas_credito',
    'facturacion.listar_notas_debito',
    'facturacion.crear',
    'facturacion.registrar_cobro',
];
