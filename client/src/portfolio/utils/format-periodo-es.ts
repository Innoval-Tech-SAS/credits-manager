import { FULL_MONTHS_ES } from '../constants/full-months-es';

/** "2026-08" -> "Agosto 2026" */
export function formatPeriodoEs(periodo: string): string {
    const [anio, mes] = periodo.split('-');
    return `${FULL_MONTHS_ES[Number(mes) - 1]} ${anio}`;
}
