import type { FC } from 'react';
import { useNavigate } from 'react-router';

import { useResource } from '../../shared/hooks/use-resource';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { AccountForm } from '../components/account-form';
import type { ApiAccount } from '../types/api-account';

export const CreateAccountPage: FC = () => {
    const navigate = useNavigate();
    const { data } = useResource<{ cuentas: ApiAccount[] }>('/contabilidad/plan-cuentas');
    const cuentas = data?.cuentas ?? [];

    const handleCreated = () => {
        navigate('/contabilidad/plan-cuentas', { replace: true, state: { message: 'Cuenta creada correctamente.' } });
    };

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Crear cuenta" />

            <div className="flex-1 rounded-2xl border border-gray-200 bg-white p-6 md:p-8 dark:border-gray-800 dark:bg-white/3">
                <AccountForm cuentas={cuentas} onSaved={handleCreated} />
            </div>
        </div>
    );
};
