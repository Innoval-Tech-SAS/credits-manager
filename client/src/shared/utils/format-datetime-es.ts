const DATE_TIME_FORMAT = new Intl.DateTimeFormat('es-CO', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false,
    timeZone: 'America/Bogota',
});

/** Instantes del backend (ISO 8601 con offset) siempre se muestran en hora de Bogotá. */
export function formatDateTimeEs(instant: string | null): string {
    if (!instant) {
        return '';
    }

    const date = new Date(instant);

    return Number.isNaN(date.getTime()) ? '' : DATE_TIME_FORMAT.format(date);
}
