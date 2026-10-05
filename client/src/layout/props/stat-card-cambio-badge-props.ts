import type { StatCardCambio } from '../types/stat-card-cambio';

export type StatCardCambioBadgeProps = {
    cambio: Pick<StatCardCambio, 'porcentaje' | 'favorable'>;
};
