import type { SelectOption } from '@ui/forms/types/select-option';

export const DRIVER_OPTIONS: SelectOption[] = [
    { value: 'smtp', label: 'Servidor SMTP (envía a internet)' },
    { value: 'registro', label: 'Prueba: no envía, solo registra en el log' },
];

export const CIFRADO_OPTIONS: SelectOption[] = [
    { value: 'tls', label: 'STARTTLS (puerto 587)' },
    { value: 'ssl', label: 'SSL/TLS implícito (puerto 465)' },
    { value: 'ninguno', label: 'Sin cifrado' },
];

export const DISPATCH_STATUS_LABELS: Record<'en_cola' | 'enviado' | 'fallido', { label: string; className: string }> = {
    en_cola: {
        label: 'En cola',
        className: 'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-orange-400',
    },
    enviado: {
        label: 'Enviado',
        className: 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500',
    },
    fallido: {
        label: 'Fallido',
        className: 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-500',
    },
};
