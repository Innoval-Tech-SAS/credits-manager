/** Raw shape of a row inside `GET /contabilidad/centros-costo/{id}/movimientos`'s paginated `data`. */
export type ApiCostCenterMovement = {
    id: string;
    tipo: 'gasto' | 'asiento';
    fecha: string;
    documento: string;
    descripcion: string | null;
    debito: string;
    credito: string;
    estado: string;
};
