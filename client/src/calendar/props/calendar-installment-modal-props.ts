import type { CalendarEventProps } from '../types/calendar-event';

export type CalendarInstallmentModalProps = {
    /** Cuota seleccionada en el calendario; nulo cuando el modal está cerrado. */
    detalle: CalendarEventProps | null;
    onClose: () => void;
};
