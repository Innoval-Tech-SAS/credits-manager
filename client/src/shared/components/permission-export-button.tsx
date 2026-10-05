import type { FC } from 'react';

import { usePermission } from '../../auth/hooks/use-permission';
import { ExportButton } from './export-button';
import type { PermissionExportButtonProps } from '../props/permission-export-button-props';

/** Botón de exportación que solo aparece con el permiso `exportar` del módulo. Cada pantalla muestra su resultado. */
export const PermissionExportButton: FC<PermissionExportButtonProps> = ({ modulo, endpoint, filters, disabled = false, onRequested, onError }) => {
    const canExport = usePermission(modulo, 'exportar');

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
