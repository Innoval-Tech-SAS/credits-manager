import type { FC } from 'react';
import { Navigate } from 'react-router';

import { useAuth } from '../hooks/use-auth';
import type { WithChildrenProps } from '../../shared/props/with-children-props';

export const RequireSuperAdmin: FC<WithChildrenProps> = ({ children }) => {
    const { user } = useAuth();

    if (!user?.isSuperAdmin) {
        return <Navigate to="/" replace />;
    }

    return <>{children}</>;
};
