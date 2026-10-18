import type { FC } from 'react';
import { useNavigate } from 'react-router';

import { useResource } from '../../shared/hooks/use-resource';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { FormSkeletonBody } from '@ui/skeleton/components/form-skeleton-body';
import { ParametrizationForm } from '../components/parametrization-form';
import type { ApiAccount } from '../../accounting/types/api-account';
import type { ApiVoucherType } from '../../accounting/types/api-voucher-type';
import type { PaginatedResponse } from '../../shared/types/paginated-response';
import type { ApiExpenseType } from '../types/api-expense-type';

export const CreateParametrizationPage: FC = () => {
    const navigate = useNavigate();

    const { data: tiposGastoData, isLoading: isLoadingTiposGasto } = useResource<{ tipos_gasto: ApiExpenseType[] }>('/gastos/tipos-gasto');
    const { data: cuentasData, isLoading: isLoadingCuentas } = useResource<{ cuentas: ApiAccount[] }>('/contabilidad/plan-cuentas');
    const { data: tiposComprobanteData, isLoading: isLoadingTiposComprobante } = useResource<PaginatedResponse<ApiVoucherType>>(
        '/contabilidad/tipos-comprobante?per_page=100',
    );

    const tiposGasto = tiposGastoData?.tipos_gasto ?? [];
    const cuentas = (cuentasData?.cuentas ?? []).filter((cuenta) => cuenta.permite_movimiento && cuenta.activa);
    const tiposComprobante = tiposComprobanteData?.data ?? [];
    const isLoading = isLoadingTiposGasto || isLoadingCuentas || isLoadingTiposComprobante;

    const handleCreated = () => {
        navigate('/gastos/parametrizaciones', { replace: true, state: { message: 'Parametrización guardada correctamente.' } });
    };

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Crear parametrización de tipo de gasto" />

            <div className="flex-1 rounded-2xl border border-gray-200 bg-white p-6 md:p-8 dark:border-gray-800 dark:bg-white/3">
                {isLoading ? (
                    <FormSkeletonBody />
                ) : (
                    <ParametrizationForm tiposGasto={tiposGasto} cuentas={cuentas} tiposComprobante={tiposComprobante} onCreated={handleCreated} />
                )}
            </div>
        </div>
    );
};
