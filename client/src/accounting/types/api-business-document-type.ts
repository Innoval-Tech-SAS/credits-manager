/** Raw shape of a row inside `GET /contabilidad/tipos-documento-negocio` and of `PATCH .../{id}`. */
export type ApiBusinessDocumentType = {
    id: number;
    codigo: 'FV' | 'CC' | 'RC' | 'CE' | 'NC' | 'ND';
    nombre: string;
    naturaleza: 'ingreso' | 'gasto' | 'recaudo' | 'pago' | 'ajuste';
    crea_saldo: boolean;
    cruza_saldo: boolean;
    activo: boolean;
};
