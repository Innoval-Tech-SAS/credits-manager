import type { ApiPermisoPanelItem } from './api-permiso';

/** Módulo del panel de permisos: sus acciones en el orden del catálogo. */
export type PermissionModule = {
    modulo: string;
    etiqueta: string;
    permisos: ApiPermisoPanelItem[];
};

/** Grupo del menú lateral con los módulos que cuelgan de él, en el orden en que llegan de `GET /permisos`. */
export type PermissionMenuGroup = {
    grupo: string;
    etiqueta: string;
    modulos: PermissionModule[];
};
