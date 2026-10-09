/** Raw shape of the `DocumentoTercero` returned by `POST /anticipos/proveedores`, `/clientes` and `/cruzar`. */
export type ApiAdvance = {
    id: number;
    tercero_id: number;
    naturaleza: 'anticipo_proveedor' | 'anticipo_cliente';
    numero_documento: string;
    fecha: string;
    /** Decimal string en pesos. */
    valor_original: string;
    /** Decimal string en pesos. */
    saldo: string;
    estado: 'pendiente' | 'abonado_parcial' | 'cancelado';
};

/** Raw shape of `POST /anticipos/cruzar`. */
export type ApiAdvanceCrossing = {
    anticipo: ApiAdvance;
    documento: ApiAdvance;
};
