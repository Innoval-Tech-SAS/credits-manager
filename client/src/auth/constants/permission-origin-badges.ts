import type { PermissionOrigin } from '../types/permission-origin';

/** Badge de origen de cada permiso en el formulario de usuario: rol (brand), excepción concedida (success) o quitada (error). */
export const PERMISSION_ORIGIN_BADGES: Record<PermissionOrigin, { label: string; className: string }> = {
    rol: { label: 'Rol', className: 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400' },
    extra: { label: 'Extra', className: 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400' },
    quitado: { label: 'Quitado', className: 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400' },
};
