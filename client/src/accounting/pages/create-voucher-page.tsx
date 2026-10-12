import type { FC } from 'react';

import { useResource } from '../../shared/hooks/use-resource';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { FormSkeletonBody } from '@ui/skeleton/components/form-skeleton-body';
import { VoucherForm } from '../components/voucher-form';
import type { ApiAccount } from '../types/api-account';
import type { ApiCostCenter } from '../types/api-cost-center';
import type { ApiVoucherType } from '../types/api-voucher-type';
import type { PaginatedResponse } from '../../shared/types/paginated-response';

export const CreateVoucherPage: FC = () => {
    const { data: cuentasData, isLoading: isLoadingCuentas } = useResource<{ cuentas: ApiAccount[] }>('/contabilidad/plan-cuentas');
    const { data: tiposData, isLoading: isLoadingTipos } = useResource<PaginatedResponse<ApiVoucherType>>(
        '/contabilidad/tipos-comprobante?per_page=100',
    );
    const { data: centrosData, isLoading: isLoadingCentros } = useResource<PaginatedResponse<ApiCostCenter>>(
        '/contabilidad/centros-costo?per_page=100',
    );

    const cuentas = (cuentasData?.cuentas ?? []).filter((cuenta) => cuenta.permite_movimiento);
    const tipos = tiposData?.data ?? [];
    const centrosCosto = (centrosData?.data ?? []).filter((centro) => centro.activo);

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Nuevo comprobante manual" />

            <div className="flex-1 rounded-2xl border border-gray-200 bg-white p-6 md:p-8 dark:border-gray-800 dark:bg-white/3">
                {(isLoadingCuentas || isLoadingTipos || isLoadingCentros) ? (
                    <FormSkeletonBody />
                ) : (
                    <VoucherForm tipos={tipos} cuentas={cuentas} centrosCosto={centrosCosto} />
                )}
            </div>
        </div>
    );
};
