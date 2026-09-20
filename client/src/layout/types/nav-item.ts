import type { LucideIcon } from 'lucide-react';

import type { PermissionAction } from '../../auth/types/user';

export type NavSubItem = {
    name: string;
    path: string;
    icon?: LucideIcon;
    adminOnly?: boolean;
    moduleKey?: string;
    /** Permission action required for this item to show up. Defaults to "listar". */
    action?: PermissionAction;
};

export type NavItem = {
    name: string;
    icon: LucideIcon;
    path?: string;
    adminOnly?: boolean;
    moduleKey?: string;
    /** Permission action required for this item to show up. Defaults to "listar". */
    action?: PermissionAction;
    subItems?: NavSubItem[];
};
