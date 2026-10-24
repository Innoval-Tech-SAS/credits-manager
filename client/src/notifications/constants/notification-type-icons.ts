import type { LucideIcon } from 'lucide-react';
import { AlertCircle, CalendarClock, CircleCheck, LockOpen } from 'lucide-react';

import type { NotificationType } from '../types/api-notification';

export const NOTIFICATION_TYPE_ICONS: Record<NotificationType, { Icon: LucideIcon; className: string }> = {
    exportacion_lista: { Icon: CircleCheck, className: 'text-success-500' },
    exportacion_fallida: { Icon: AlertCircle, className: 'text-error-500' },
    reapertura_solicitada: { Icon: LockOpen, className: 'text-warning-500' },
    reapertura_autorizada: { Icon: CircleCheck, className: 'text-success-500' },
    reapertura_rechazada: { Icon: AlertCircle, className: 'text-error-500' },
    importacion_validada: { Icon: CircleCheck, className: 'text-brand-500' },
    importacion_completada: { Icon: CircleCheck, className: 'text-success-500' },
    importacion_fallida: { Icon: AlertCircle, className: 'text-error-500' },
    extracto_bancario_cargado: { Icon: CircleCheck, className: 'text-success-500' },
    extracto_bancario_rechazado: { Icon: AlertCircle, className: 'text-error-500' },
    obligacion_tributaria_por_vencer: { Icon: CalendarClock, className: 'text-warning-500' },
};
