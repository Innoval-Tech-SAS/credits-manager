import type { FC } from 'react';
import { Navigate } from 'react-router';

import { useAuth } from '../hooks/use-auth';
import { canAccessAudit } from '../utils/can-access-audit';
import type { WithChildrenProps } from '../../shared/props/with-children-props';

export const RequireAuditAccess: FC<WithChildrenProps> = ({ children }) => {
    const { user } = useAuth();

    if (!canAccessAudit(user, 'listar')) {
        return <Navigate to="/" replace />;
    }

    return <>{children}</>;
};
