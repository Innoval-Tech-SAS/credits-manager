/** Raw shape of `GET /contabilidad/documentos-tercero/{id}/trazabilidad`. */
export type ApiDocumentTraceability = {
    documento: {
        id: number;
        tipo_documento: string;
        numero_documento: string;
        fecha: string;
        valor_original: string;
        saldo: string;
        estado: 'pendiente' | 'abonado_parcial' | 'cancelado' | 'anulado';
        tercero: { id: number; nombre: string };
    };
    pasos: {
        id: number;
        modulo_aplicacion: string;
        aplicacion_id: number;
        valor_aplicado: string;
        fecha: string;
        reversado_en: string | null;
        saldo_resultante: string;
    }[];
};
