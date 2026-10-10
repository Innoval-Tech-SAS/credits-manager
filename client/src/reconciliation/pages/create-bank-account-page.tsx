import type { FC } from 'react';
import { useNavigate } from 'react-router';

import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { BankAccountForm } from '../components/bank-account-form';

export const CreateBankAccountPage: FC = () => {
    const navigate = useNavigate();

    const handleCreated = () => {
        navigate('/conciliacion/cuentas-bancarias', { replace: true, state: { message: 'Cuenta bancaria creada correctamente.' } });
    };

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Crear cuenta bancaria" />

            <div className="flex-1 rounded-2xl border border-gray-200 bg-white p-6 md:p-8 dark:border-gray-800 dark:bg-white/3">
                <BankAccountForm onCreated={handleCreated} />
            </div>
        </div>
    );
};
