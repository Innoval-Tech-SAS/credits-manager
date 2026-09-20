import type { PermissionAction, User } from '../types/user';

export function hasPermission(user: User | null, modulo: string, accion: PermissionAction): boolean {
    if (!user) {
        return false;
    }

    return user.permissions.some((permiso) => permiso.modulo === modulo && permiso.accion === accion);
}
