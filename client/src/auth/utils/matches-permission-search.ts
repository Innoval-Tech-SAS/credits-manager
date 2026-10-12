import type { ApiPermisoPanelItem } from '../types/api-permiso';

function normalizar(texto: string): string {
    return texto.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
}

/** Búsqueda sin tildes ni mayúsculas sobre los nombres legibles del permiso, su módulo y su grupo del menú. */
export function matchesPermissionSearch(permiso: ApiPermisoPanelItem, busqueda: string): boolean {
    const termino = normalizar(busqueda.trim());

    if (termino === '') {
        return true;
    }

    return [permiso.accion_etiqueta, permiso.modulo_etiqueta, permiso.grupo_etiqueta].some((texto) => normalizar(texto).includes(termino));
}
