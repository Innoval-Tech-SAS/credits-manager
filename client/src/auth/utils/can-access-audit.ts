import type { User } from '../types/user';
import { hasPermission } from './has-permission';
import { isCompanyAdmin } from './is-company-admin';

/**
 * Regla de acceso a la bitácora (idéntica a la del backend): superadmin, administrador de empresa o
 * permiso `auditoria.<acción>`. El acceso del admin y del superadmin no depende del permiso asignado.
 */
export function canAccessAudit(user: User | null, action: 'listar' | 'exportar'): boolean {
    if (!user) {
        return false;
    }

    return user.isSuperAdmin || isCompanyAdmin(user) || hasPermission(user, 'auditoria', action);
}
