import type { ApiRoleRef } from './api-role';
import type { ApiPermisoExcepcion } from './api-permiso';

/** Raw shape of the `usuario` object returned by `GET /usuarios/{id}`. */
export type ApiUserDetail = {
    id: number;
    nombre: string;
    usuario: string;
    rol: ApiRoleRef | null;
    permisos_extra: ApiPermisoExcepcion[];
    activo: boolean;
    es_asesor_global: boolean;
};
