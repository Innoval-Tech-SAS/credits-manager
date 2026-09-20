import type { FC } from 'react';
import { useNavigate } from 'react-router';

import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { ExpenseTypeForm } from '../components/expense-type-form';

export const CreateExpenseTypePage: FC = () => {
    const navigate = useNavigate();

    const handleCreated = () => {
        navigate('/gastos/tipos-gasto', { replace: true, state: { message: 'Tipo de gasto creado correctamente.' } });
    };

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Crear tipo de gasto" />

            <div className="flex-1 rounded-2xl border border-gray-200 bg-white p-6 md:p-8 dark:border-gray-800 dark:bg-white/3">
                <ExpenseTypeForm onCreated={handleCreated} />
            </div>
        </div>
    );
};
