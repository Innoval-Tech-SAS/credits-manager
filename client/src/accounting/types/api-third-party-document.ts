/** Raw shape returned by `GET /terceros/{id}/documentos-pendientes` (rows). */
export type ApiThirdPartyDocument = {
    id: number;
    tercero_id: number;
    naturaleza: 'por_pagar' | 'por_cobrar';
    tipo_documento: string;
    numero_documento: string;
    fecha: string;
    concepto: string;
    valor_original: string;
    saldo: string;
    estado: 'pendiente' | 'cancelado';
};
