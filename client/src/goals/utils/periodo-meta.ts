import type { SelectOption } from '@ui/forms/types/select-option';

const FORMATO_MES = new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Bogota', year: 'numeric', month: '2-digit' });

/** Mes en curso en Colombia, como `YYYY-MM`. */
export function periodoActual(fecha: Date = new Date()): string {
    const partes = FORMATO_MES.formatToParts(fecha);
    const anio = partes.find((parte) => parte.type === 'year')?.value;
    const mes = partes.find((parte) => parte.type === 'month')?.value;

    return `${anio}-${mes}`;
}

/** Opciones de período: el mes en curso y los anteriores, del más reciente al más antiguo. */
export function opcionesDePeriodo(cantidad = 12): SelectOption[] {
    const [anio, mes] = periodoActual().split('-').map(Number);
    const formato = new Intl.DateTimeFormat('es-CO', { month: 'long', year: 'numeric' });

    return Array.from({ length: cantidad }, (_, indice) => {
        const total = anio * 12 + (mes - 1) - indice;
        const anioOpcion = Math.floor(total / 12);
        const mesOpcion = (total % 12) + 1;
        const codigo = `${anioOpcion}-${String(mesOpcion).padStart(2, '0')}`;

        return { value: codigo, label: formato.format(new Date(anioOpcion, mesOpcion - 1, 1)) };
    });
}

/** Porcentaje de la barra: nunca más de 100 aunque la meta se haya superado. */
export function anchoDeBarra(porcentaje: string): string {
    const numero = Math.min(Math.max(parseFloat(porcentaje) || 0, 0), 100);

    return `${numero}%`;
}
