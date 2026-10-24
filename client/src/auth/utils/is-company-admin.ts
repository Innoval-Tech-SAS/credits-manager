import type { User } from '../types/user';

/** Mismo criterio que el middleware `admin` del backend: el rol `admin` de la empresa, no el Super Admin. */
export function isCompanyAdmin(user: User | null): boolean {
    return user?.role === 'admin';
}
