import type { PermissionAction } from '../types/user';
import { useAuth } from './use-auth';
import { hasPermission } from '../utils/has-permission';

export function usePermission(modulo: string, accion: PermissionAction): boolean {
    const { user } = useAuth();

    return hasPermission(user, modulo, accion);
}
