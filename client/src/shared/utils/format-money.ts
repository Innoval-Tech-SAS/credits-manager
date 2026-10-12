export function formatMoney(value: string | number): string {
    const numeric = typeof value === 'string' ? parseFloat(value) : value;
    const formatted = Math.abs(numeric).toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return numeric < 0 ? `-$${formatted}` : `$${formatted}`;
}
