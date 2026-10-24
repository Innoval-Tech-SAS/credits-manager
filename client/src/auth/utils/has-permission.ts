import type { PermissionAction, User } from '../types/user';
import { isCompanyAdmin } from './is-company-admin';

/** Replica `Usuario::tienePermiso` del backend: el admin de empresa tiene todo el catálogo aunque se le revoque algo puntual. */
export function hasPermission(user: User | null, modulo: string, accion: PermissionAction): boolean {
    if (!user) {
        return false;
    }

    if (isCompanyAdmin(user)) {
        return true;
    }

    return user.permissions.some((permiso) => permiso.modulo === modulo && permiso.accion === accion);
}
