import type { FC } from 'react';
import { Navigate } from 'react-router';

import { useAuth } from '../../auth/hooks/use-auth';
import { useCompany } from '../hooks/use-company';
import type { WithChildrenProps } from '../../shared/props/with-children-props';

export const RequireCompany: FC<WithChildrenProps> = ({ children }) => {
    const { user } = useAuth();
    const { company, isLoading } = useCompany();

    if (isLoading) {
        return null;
    }

    if (!company) {
        if (user?.isSuperAdmin) {
            return <>{children}</>;
        }

        if (user?.role === 'admin') {
            return <Navigate to="/onboarding/empresa" replace />;
        }

        return (
            <div className="flex min-h-screen items-center justify-center bg-gray-50 px-4 dark:bg-gray-900">
                <div className="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-8 text-center shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
                    <h1 className="text-title-sm font-semibold text-gray-800 dark:text-white/90">Sistema no configurado</h1>
                    <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Contacta a un administrador para completar la configuración inicial de la empresa.
                    </p>
                </div>
            </div>
        );
    }

    return <>{children}</>;
};
