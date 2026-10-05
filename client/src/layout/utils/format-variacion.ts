const FORMATO_VARIACION = new Intl.NumberFormat('es-CO', {
    signDisplay: 'exceptZero',
    minimumFractionDigits: 1,
    maximumFractionDigits: 1,
});

export function formatVariacion(porcentaje: number | null): string {
    return porcentaje === null ? 'sin base' : `${FORMATO_VARIACION.format(porcentaje)} %`;
}
