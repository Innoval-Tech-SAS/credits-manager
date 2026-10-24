const UNITS = ['B', 'KB', 'MB', 'GB', 'TB'];

/** Tamaños en base 1024 con una cifra decimal: "12,4 GB". */
export function formatBytes(bytes: number | null): string {
    if (bytes === null) {
        return '—';
    }

    let value = bytes;
    let unit = 0;

    while (value >= 1024 && unit < UNITS.length - 1) {
        value /= 1024;
        unit++;
    }

    return `${value.toLocaleString('es-CO', { maximumFractionDigits: unit === 0 ? 0 : 1 })} ${UNITS[unit]}`;
}
