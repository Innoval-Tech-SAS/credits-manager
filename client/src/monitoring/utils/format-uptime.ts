/** Tiempo encendido legible: "12 d 4 h", "3 h 20 min" o "45 min". */
export function formatUptime(seconds: number | null): string {
    if (seconds === null) {
        return '—';
    }

    const days = Math.floor(seconds / 86_400);
    const hours = Math.floor((seconds % 86_400) / 3_600);
    const minutes = Math.floor((seconds % 3_600) / 60);

    if (days > 0) {
        return `${days} d ${hours} h`;
    }

    if (hours > 0) {
        return `${hours} h ${minutes} min`;
    }

    return `${minutes} min`;
}
