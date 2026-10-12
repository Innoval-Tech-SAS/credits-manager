import type { User } from '../types/user';
import { hasPermission } from './has-permission';

/** Para pantallas que sirven a varias pantallas del menú: basta con uno de los permisos "modulo.accion". */
export function hasAnyPermission(user: User | null, permisos: string[]): boolean {
    return permisos.some((clave) => {
        const [modulo, accion] = clave.split('.');

        return hasPermission(user, modulo, accion);
    });
}
