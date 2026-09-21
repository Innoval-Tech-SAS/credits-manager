import type { SelectOption } from '@ui/forms/types/select-option';
import type { AuditAction } from '../types/audit-action';

export const AUDIT_ACTION_LABELS: Record<AuditAction, string> = {
    crear: 'Crear',
    modificar: 'Modificar',
    eliminar: 'Eliminar',
    aprobar: 'Aprobar',
    desembolsar: 'Desembolsar',
    cancelar: 'Cancelar',
    anular: 'Anular',
    revertir: 'Revertir',
    login: 'Inicio de sesión',
    logout: 'Cierre de sesión',
    login_fallido: 'Inicio de sesión fallido',
    cambio_permisos: 'Cambio de permisos',
    importar: 'Importar',
    exportar: 'Exportar',
    recuperar: 'Recuperar',
    causar: 'Causar',
    contabilizar: 'Contabilizar',
    reversar: 'Reversar',
    cerrar_periodo: 'Cerrar período',
    reabrir_periodo: 'Reabrir período',
};

export const AUDIT_ACTION_OPTIONS: SelectOption[] = (Object.keys(AUDIT_ACTION_LABELS) as AuditAction[]).map((action) => ({
    value: action,
    label: AUDIT_ACTION_LABELS[action],
}));

const SUCCESS = 'bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-400';
const ERROR = 'bg-error-50 text-error-600 dark:bg-error-500/10 dark:text-error-400';
const WARNING = 'bg-warning-50 text-warning-600 dark:bg-warning-500/10 dark:text-orange-400';
const BRAND = 'bg-brand-50 text-brand-500 dark:bg-brand-500/15 dark:text-brand-400';

/** Badge semántico por acción: verde = alta/avance, rojo = anulación/baja, ámbar = cambio sensible, azul = sesión y datos. */
export const AUDIT_ACTION_BADGE_CLASSES: Record<AuditAction, string> = {
    crear: SUCCESS,
    aprobar: SUCCESS,
    desembolsar: SUCCESS,
    causar: SUCCESS,
    contabilizar: SUCCESS,
    modificar: WARNING,
    cambio_permisos: WARNING,
    cerrar_periodo: WARNING,
    reabrir_periodo: WARNING,
    eliminar: ERROR,
    anular: ERROR,
    cancelar: ERROR,
    revertir: ERROR,
    reversar: ERROR,
    login_fallido: ERROR,
    login: BRAND,
    logout: BRAND,
    importar: BRAND,
    exportar: BRAND,
    recuperar: BRAND,
};
