import type { FC } from 'react';

import { useResource } from '../../shared/hooks/use-resource';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { Spinner } from '@ui/spinner/components/spinner';
import { VoucherForm } from '../components/voucher-form';
import type { ApiAccount } from '../types/api-account';
import type { ApiVoucherType } from '../types/api-voucher-type';
import type { PaginatedResponse } from '../../shared/types/paginated-response';

export const CreateVoucherPage: FC = () => {
    const { data: cuentasData, isLoading: isLoadingCuentas } = useResource<{ cuentas: ApiAccount[] }>('/contabilidad/plan-cuentas');
    const { data: tiposData, isLoading: isLoadingTipos } = useResource<PaginatedResponse<ApiVoucherType>>(
        '/contabilidad/tipos-comprobante?per_page=100',
    );

    const cuentas = (cuentasData?.cuentas ?? []).filter((cuenta) => cuenta.permite_movimiento);
    const tipos = tiposData?.data ?? [];

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Nuevo comprobante manual" />

            <div className="flex-1 rounded-2xl border border-gray-200 bg-white p-6 md:p-8 dark:border-gray-800 dark:bg-white/3">
                {(isLoadingCuentas || isLoadingTipos) ? (
                    <div className="flex justify-center py-12">
                        <Spinner size="lg" />
                    </div>
                ) : (
                    <VoucherForm tipos={tipos} cuentas={cuentas} />
                )}
            </div>
        </div>
    );
};
