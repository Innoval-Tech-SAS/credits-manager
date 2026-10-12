import type { ApiPermisoPanelItem } from '../types/api-permiso';
import type { PermissionMenuGroup } from '../types/permission-menu-group';

/** Agrupa el catálogo por grupo del menú y módulo, conservando el orden en que lo devuelve `GET /permisos`. */
export function groupPermissionsByMenu(permisos: ApiPermisoPanelItem[]): PermissionMenuGroup[] {
    const grupos: PermissionMenuGroup[] = [];

    for (const permiso of permisos) {
        let grupo = grupos.find((item) => item.grupo === permiso.grupo);
        if (!grupo) {
            grupo = { grupo: permiso.grupo, etiqueta: permiso.grupo_etiqueta, modulos: [] };
            grupos.push(grupo);
        }

        let modulo = grupo.modulos.find((item) => item.modulo === permiso.modulo);
        if (!modulo) {
            modulo = { modulo: permiso.modulo, etiqueta: permiso.modulo_etiqueta, permisos: [] };
            grupo.modulos.push(modulo);
        }

        modulo.permisos.push(permiso);
    }

    return grupos;
}
