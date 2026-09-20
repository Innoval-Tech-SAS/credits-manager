import type { FC } from 'react';
import { Navigate } from 'react-router';

import { useAuth } from '../hooks/use-auth';
import type { WithChildrenProps } from '../../shared/props/with-children-props';

export const RequireAdmin: FC<WithChildrenProps> = ({ children }) => {
    const { user } = useAuth();

    if (user?.role !== 'admin') {
        return <Navigate to="/" replace />;
    }

    return <>{children}</>;
};
