import type { FC } from 'react';

import { usePermission } from '../../auth/hooks/use-permission';
import { ExportButton } from './export-button';
import type { PermissionExportButtonProps } from '../props/permission-export-button-props';

/** Botón de exportación que solo aparece con el permiso propio de esa exportación. Cada pantalla muestra su resultado. */
export const PermissionExportButton: FC<PermissionExportButtonProps> = ({ permiso, endpoint, filters, disabled = false, onRequested, onError }) => {
    const [modulo, accion] = permiso.split('.');
    const canExport = usePermission(modulo, accion);

    if (!canExport) {
        return null;
    }

    return (
        <ExportButton
            endpoint={endpoint}
            filters={filters}
            disabled={disabled}
            onRequested={() => onRequested?.()}
            onError={(message) => onError?.(message)}
        />
    );
};
