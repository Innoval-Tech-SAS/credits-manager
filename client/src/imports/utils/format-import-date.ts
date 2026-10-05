/** La API devuelve `YYYY-MM-DD HH:MM:SS` (hora de Bogotá): se muestra sin zona para no reinterpretarla. */
export function formatImportDate(value: string | null): string {
    return value ? value.replace('T', ' ').slice(0, 16) : '—';
}
