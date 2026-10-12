import type { ReactNode } from 'react';

export type RequireAnyPermissionProps = {
    /** Claves "modulo.accion"; basta con tener una. */
    permisos: string[];
    children: ReactNode;
};
