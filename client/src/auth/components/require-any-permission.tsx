import type { FC } from 'react';
import { Navigate } from 'react-router';

import { useAuth } from '../hooks/use-auth';
import type { RequireAnyPermissionProps } from '../props/require-any-permission-props';
import { hasAnyPermission } from '../utils/has-any-permission';

export const RequireAnyPermission: FC<RequireAnyPermissionProps> = ({ permisos, children }) => {
    const { user } = useAuth();

    if (!hasAnyPermission(user, permisos)) {
        return <Navigate to="/" replace />;
    }

    return <>{children}</>;
};
