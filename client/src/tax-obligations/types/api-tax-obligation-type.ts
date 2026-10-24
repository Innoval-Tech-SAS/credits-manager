export type ApiTaxObligationPeriodicity = 'mensual' | 'bimestral' | 'cuatrimestral' | 'anual' | 'unica';

/** Raw shape returned by `GET /obligaciones-tributarias/tipos`, `POST .../tipos` and `PUT .../tipos/{id}`. */
export type ApiTaxObligationType = {
    id: number;
    nombre: string;
    periodicidad: ApiTaxObligationPeriodicity;
    dias_alerta_previa: number;
    activo: boolean;
};
