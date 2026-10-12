import type { ApiPermisoPanelItem } from '../types/api-permiso';
import type { PermissionModule } from '../types/permission-menu-group';
import type { PermissionOrigin } from '../types/permission-origin';

export type PermissionModuleCardProps = {
    modulo: PermissionModule;
    /** Acciones que se muestran: todas, o las que coinciden con la búsqueda. */
    visibles: ApiPermisoPanelItem[];
    isExpanded: boolean;
    onToggleExpanded: () => void;
    isChecked: (permiso: ApiPermisoPanelItem) => boolean;
    canChange: (permiso: ApiPermisoPanelItem) => boolean;
    onChange: (permisoIds: number[], checked: boolean) => void;
    getOrigin?: (permiso: ApiPermisoPanelItem) => PermissionOrigin | null;
};
