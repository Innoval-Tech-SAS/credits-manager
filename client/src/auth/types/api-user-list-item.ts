import type { ApiRoleRef } from './api-role';

/** Raw shape of a row in the `GET /usuarios` paginated response. */
export type ApiUserListItem = {
    id: number;
    nombre: string;
    usuario: string;
    rol: ApiRoleRef | null;
    activo: boolean;
    creado_en: string | null;
};
