export function formatMoney(value: string | number): string {
    const numeric = typeof value === 'string' ? parseFloat(value) : value;
    return `$${numeric.toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}
