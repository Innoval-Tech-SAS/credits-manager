import type { LucideIcon } from 'lucide-react';

import type { PermissionAction, User } from '../../auth/types/user';

export type NavSubItem = {
    name: string;
    path: string;
    icon?: LucideIcon;
    adminOnly?: boolean;
    moduleKey?: string;
    /** Permission action required for this item to show up. Defaults to "listar". */
    action?: PermissionAction;
    /** Regla propia de visibilidad, para accesos que no se reducen a un permiso (p. ej. auditoría: superadmin, admin o permiso). */
    isVisible?: (user: User | null) => boolean;
};

export type NavItem = {
    name: string;
    icon: LucideIcon;
    path?: string;
    adminOnly?: boolean;
    moduleKey?: string;
    /** Permission action required for this item to show up. Defaults to "listar". */
    action?: PermissionAction;
    isVisible?: (user: User | null) => boolean;
    subItems?: NavSubItem[];
};
