import type { FC } from 'react';
import { Navigate } from 'react-router';

import { useAuth } from '../hooks/use-auth';
import type { WithChildrenProps } from '../../shared/props/with-children-props';

export const RequireAuth: FC<WithChildrenProps> = ({ children }) => {
    const { user, isLoading } = useAuth();

    if (isLoading) {
        return null;
    }

    if (!user) {
        return <Navigate to="/login" replace />;
    }

    return <>{children}</>;
};
