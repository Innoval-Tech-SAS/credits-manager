import type { FC } from 'react';
import { Navigate } from 'react-router';

import { useAuth } from '../hooks/use-auth';
import { isCompanyAdmin } from '../utils/is-company-admin';
import type { WithChildrenProps } from '../../shared/props/with-children-props';

export const RequireAdmin: FC<WithChildrenProps> = ({ children }) => {
    const { user } = useAuth();

    if (!isCompanyAdmin(user)) {
        return <Navigate to="/" replace />;
    }

    return <>{children}</>;
};
