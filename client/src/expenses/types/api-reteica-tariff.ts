/** Raw shape of a row returned by `GET /gastos/tarifas-reteica` and `POST /gastos/tarifas-reteica`. */
export type ApiReteicaTariff = {
    id: number;
    municipio_id: number;
    actividad_economica_id: number;
    fecha_desde: string;
    fecha_hasta: string | null;
    tarifa: string;
    municipio?: { id: number; nombre: string };
    actividad_economica?: { id: number; nombre: string };
};
