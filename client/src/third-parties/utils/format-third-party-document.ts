import type { ApiThirdParty } from '../types/api-third-party';

/** "CC 1000000001" o "NIT 900123456-8": el NIT siempre lleva su dígito de verificación. */
export function formatThirdPartyDocument(tercero: Pick<ApiThirdParty, 'tipo_documento' | 'numero_documento' | 'digito_verificacion'>): string {
    const documento = tercero.digito_verificacion ? `${tercero.numero_documento}-${tercero.digito_verificacion}` : tercero.numero_documento;

    return `${tercero.tipo_documento.toUpperCase()} ${documento}`;
}
