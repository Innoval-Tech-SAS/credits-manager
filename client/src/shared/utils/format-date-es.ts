import { MONTHS_ES } from '../constants/months-es';

export function formatDateEs(fecha: string | null): string {
    if (!fecha) {
        return '';
    }

    const date = new Date(`${fecha}T00:00:00`);
    return `${String(date.getDate()).padStart(2, '0')} ${MONTHS_ES[date.getMonth()]} ${date.getFullYear()}`;
}
