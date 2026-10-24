import type { FC } from 'react';
import { useNavigate, useParams } from 'react-router';

import { useResource } from '../../shared/hooks/use-resource';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { FormSkeletonBody } from '@ui/skeleton/components/form-skeleton-body';
import { ExpenseTypeForm } from '../components/expense-type-form';
import type { ApiExpenseType } from '../types/api-expense-type';

export const EditExpenseTypePage: FC = () => {
    const navigate = useNavigate();
    const { id } = useParams<{ id: string }>();

    const { data, error, isLoading } = useResource<{ tipo_gasto: ApiExpenseType }>(`/gastos/tipos-gasto/${id}`);
    const tipoGasto = data?.tipo_gasto ?? null;

    const handleSaved = () => {
        navigate('/gastos/tipos-gasto', { replace: true, state: { message: 'Tipo de gasto actualizado correctamente.' } });
    };

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Editar tipo de gasto" />

            <div className="flex-1 rounded-2xl border border-gray-200 bg-white p-6 md:p-8 dark:border-gray-800 dark:bg-white/3">
                {error && (
                    <div className="mb-6 rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                        {error}
                    </div>
                )}

                {isLoading && (
                    <FormSkeletonBody />
                )}

                {tipoGasto && <ExpenseTypeForm expenseTypeId={tipoGasto.id} initialData={tipoGasto} onSaved={handleSaved} />}
            </div>
        </div>
    );
};
