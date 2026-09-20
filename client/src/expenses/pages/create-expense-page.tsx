import type { FC } from 'react';

import { useResource } from '../../shared/hooks/use-resource';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { Spinner } from '@ui/spinner/components/spinner';
import { ExpenseForm } from '../components/expense-form';
import type { ApiExpenseType } from '../types/api-expense-type';
import type { ApiMunicipality } from '../types/api-municipality';

export const CreateExpensePage: FC = () => {
    const { data: tiposGastoData, isLoading: isLoadingTiposGasto } = useResource<{ tipos_gasto: ApiExpenseType[] }>('/gastos/tipos-gasto');
    const { data: municipiosData, isLoading: isLoadingMunicipios } = useResource<{ municipios: ApiMunicipality[] }>('/gastos/municipios');

    const tiposGasto = (tiposGastoData?.tipos_gasto ?? []).filter((tipo) => tipo.activo);
    const municipios = municipiosData?.municipios ?? [];
    const isLoading = isLoadingTiposGasto || isLoadingMunicipios;

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Causar gasto" />

            <div className="flex-1 rounded-2xl border border-gray-200 bg-white p-6 md:p-8 dark:border-gray-800 dark:bg-white/3">
                {isLoading ? (
                    <div className="flex justify-center py-12">
                        <Spinner size="lg" />
                    </div>
                ) : (
                    <ExpenseForm tiposGasto={tiposGasto} municipios={municipios} />
                )}
            </div>
        </div>
    );
};
