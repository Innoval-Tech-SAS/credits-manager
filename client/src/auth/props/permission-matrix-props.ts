import type { ReactNode } from 'react';

import type { ApiPermisoPanelItem } from '../types/api-permiso';
import type { PermissionOrigin } from '../types/permission-origin';

export type PermissionMatrixProps = {
    /** Catálogo de `GET /permisos`, ya ordenado como el menú. */
    permisos: ApiPermisoPanelItem[];
    isChecked: (permiso: ApiPermisoPanelItem) => boolean;
    /** `false` deja la casilla deshabilitada (p. ej. quien no es admin no concede lo que no tiene). */
    canChange: (permiso: ApiPermisoPanelItem) => boolean;
    /** Marca o desmarca de una vez los permisos indicados (una acción o un módulo completo). */
    onChange: (permisoIds: number[], checked: boolean) => void;
    /** Solo en el formulario de usuario: de dónde sale cada permiso (rol, excepción concedida o quitada). */
    getOrigin?: (permiso: ApiPermisoPanelItem) => PermissionOrigin | null;
    /** Contenido bajo el encabezado, p. ej. el resumen de excepciones del usuario. */
    summary?: ReactNode;
    isLoading?: boolean;
};
