import type { ReactNode } from 'react';

import type { PermissionAction } from '../types/user';

export type RequireModulePermissionProps = {
    modulo: string;
    accion: PermissionAction;
    children: ReactNode;
};
