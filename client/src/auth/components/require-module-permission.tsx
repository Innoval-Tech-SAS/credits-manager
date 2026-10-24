import type { FC } from 'react';
import { Navigate } from 'react-router';

import { usePermission } from '../hooks/use-permission';
import type { RequireModulePermissionProps } from '../props/require-module-permission-props';

export const RequireModulePermission: FC<RequireModulePermissionProps> = ({ modulo, accion, children }) => {
    const permitido = usePermission(modulo, accion);

    if (!permitido) {
        return <Navigate to="/" replace />;
    }

    return <>{children}</>;
};
