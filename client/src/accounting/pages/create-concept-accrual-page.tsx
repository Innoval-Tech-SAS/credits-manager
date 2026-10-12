import type { FC } from 'react';

import { useResource } from '../../shared/hooks/use-resource';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { FormSkeletonBody } from '@ui/skeleton/components/form-skeleton-body';
import { ConceptAccrualForm } from '../components/concept-accrual-form';
import type { ApiExpenseType } from '../../expenses/types/api-expense-type';

export const CreateConceptAccrualPage: FC = () => {
    const { data: tiposGastoData, isLoading } = useResource<{ tipos_gasto: ApiExpenseType[] }>('/gastos/tipos-gasto');

    const tiposGasto = (tiposGastoData?.tipos_gasto ?? []).filter((tipo) => tipo.activo);

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Causar con concepto" />

            <div className="flex-1 rounded-2xl border border-gray-200 bg-white p-6 md:p-8 dark:border-gray-800 dark:bg-white/3">
                {isLoading ? (
                    <FormSkeletonBody />
                ) : (
                    <ConceptAccrualForm tiposGasto={tiposGasto} />
                )}
            </div>
        </div>
    );
};
