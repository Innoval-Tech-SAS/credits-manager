import type { ApiRoleWithPermisos } from './api-role';

export type BuildRoleTableColumnsOptions = {
    onEdit: (row: ApiRoleWithPermisos) => void;
    onDelete: (row: ApiRoleWithPermisos) => void;
};
