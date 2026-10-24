import type { ApiTaxObligationPeriodicity } from './api-tax-obligation-type';

export type ApiTaxObligationStatus = 'pendiente' | 'cumplida' | 'no_aplica';

/** Mismo criterio que `App\Calendario\Services\CalendarioService::colorPara`: el backend decide el color, no el cliente. */
export type ApiTaxObligationColor = 'success' | 'warning' | 'danger' | 'primary';

/** Raw shape returned by `GET /obligaciones-tributarias`, `POST /obligaciones-tributarias`, `PUT /obligaciones-tributarias/{id}` and `PUT /obligaciones-tributarias/{id}/estado`. */
export type ApiTaxObligation = {
    id: number;
    tipo_obligacion: { id: number; nombre: string; periodicidad: ApiTaxObligationPeriodicity };
    periodo: string;
    fecha_limite: string;
    responsable: { id: number; nombre: string } | null;
    estado: ApiTaxObligationStatus;
    observaciones: string | null;
    cumplida_en: string | null;
    vencida: boolean;
    color: ApiTaxObligationColor;
};
