import type { LucideIcon } from 'lucide-react';

import { NAV_ITEMS, SUPERADMIN_NAV_ITEMS } from '../constants/nav-items';
import { isNavPathActive } from './is-nav-path-active';

export type NavBreadcrumbItem = {
    name: string;
    icon?: LucideIcon;
};

/** Ruta módulo > submódulo (sin "Inicio") tomada del sidebar para la ruta actual. */
export const findNavBreadcrumb = (pathname: string): NavBreadcrumbItem[] => {
    if (pathname === '/') {
        return [];
    }

    let bestTrail: NavBreadcrumbItem[] | undefined;
    let bestPathLength = -1;

    for (const module of [...NAV_ITEMS, ...SUPERADMIN_NAV_ITEMS]) {
        if (module.subItems) {
            for (const subItem of module.subItems) {
                if (isNavPathActive(pathname, subItem.path) && subItem.path.length > bestPathLength) {
                    bestTrail = [
                        { name: module.name, icon: module.icon },
                        { name: subItem.name, icon: subItem.icon },
                    ];
                    bestPathLength = subItem.path.length;
                }
            }
        } else if (module.path && isNavPathActive(pathname, module.path) && module.path.length > bestPathLength) {
            bestTrail = [{ name: module.name, icon: module.icon }];
            bestPathLength = module.path.length;
        }
    }

    return bestTrail ?? [];
};
