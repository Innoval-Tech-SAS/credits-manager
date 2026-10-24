export function formatPercentage(value: number | null | undefined): string {
    return value === null || value === undefined ? '—' : `${value.toLocaleString('es-CO', { maximumFractionDigits: 1 })} %`;
}
