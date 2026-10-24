import type { ApiPermisoCatalogItem } from './api-permiso';

/** Raw shape of a `rol` row inside `GET /roles`'s paginated `data`, and nested in user responses. */
export type ApiRoleRef = {
    id: number;
    nombre: string;
    descripcion: string | null;
};

/** A role as returned by `GET /roles`, with its baseline permissions eager-loaded. */
export type ApiRoleWithPermisos = ApiRoleRef & {
    permisos: ApiPermisoCatalogItem[];
};
