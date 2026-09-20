import type { ApiPermisoCatalogItem } from '../types/api-permiso';

export function groupPermisosByModulo(permisos: ApiPermisoCatalogItem[]): [string, ApiPermisoCatalogItem[]][] {
    const grupos = new Map<string, ApiPermisoCatalogItem[]>();

    for (const permiso of permisos) {
        const lista = grupos.get(permiso.modulo) ?? [];
        lista.push(permiso);
        grupos.set(permiso.modulo, lista);
    }

    return Array.from(grupos.entries());
}
