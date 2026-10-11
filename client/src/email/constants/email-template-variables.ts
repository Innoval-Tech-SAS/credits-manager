import type { EmailType } from '../types/email-type';
import type { EmailTemplateVariable } from '../types/email-template-variable';

/**
 * Catálogo de variables por tipo de plantilla, calcado de `CorreoConstants::variables()` en el backend.
 * El renderizador solo valida contra ese catálogo del servidor; aquí se repite para mostrarlo en el editor.
 */
export const EMAIL_TEMPLATE_VARIABLES: Record<EmailType, EmailTemplateVariable[]> = {
    cobranza: [
        { clave: 'cliente', descripcion: 'Nombre del cliente', origen: 'terceros.nombre (vía clientes)' },
        { clave: 'empresa', descripcion: 'Nombre de la empresa', origen: 'empresas.nombre_empresa' },
        { clave: 'credito', descripcion: 'Código del crédito', origen: 'creditos.codigo' },
        { clave: 'cuota', descripcion: 'Número de la cuota', origen: 'cuotas.numero_cuota' },
        { clave: 'valor', descripcion: 'Valor de la cuota, en pesos', origen: 'cuotas.valor' },
        { clave: 'fecha_vencimiento', descripcion: 'Fecha de vencimiento de la cuota', origen: 'cuotas.fecha_vencimiento' },
    ],
    facturacion: [
        { clave: 'cliente', descripcion: 'Nombre del cliente', origen: 'terceros.nombre (vía clientes)' },
        { clave: 'empresa', descripcion: 'Nombre de la empresa', origen: 'empresas.nombre_empresa' },
        { clave: 'tipo_documento', descripcion: 'Factura, nota crédito o nota débito', origen: 'documentos_facturacion.tipo' },
        { clave: 'numero_documento', descripcion: 'Número del documento', origen: 'documentos_facturacion.numero' },
        { clave: 'valor_total', descripcion: 'Valor total del documento, en pesos', origen: 'lineas_documento_facturacion.valor (suma)' },
        { clave: 'fecha', descripcion: 'Fecha de emisión del documento', origen: 'documentos_facturacion.fecha' },
        { clave: 'fecha_vencimiento', descripcion: 'Fecha de vencimiento (solo nota débito)', origen: 'documentos_facturacion.fecha_vencimiento' },
    ],
};
