import type { FC } from 'react';
import { useNavigate, useParams } from 'react-router';

import { useResource } from '../../shared/hooks/use-resource';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { FormSkeletonBody } from '@ui/skeleton/components/form-skeleton-body';
import { VoucherTypeForm } from '../components/voucher-type-form';
import type { ApiVoucherType } from '../types/api-voucher-type';

export const EditVoucherTypePage: FC = () => {
    const navigate = useNavigate();
    const { id } = useParams<{ id: string }>();

    const { data, error, isLoading } = useResource<{ tipo: ApiVoucherType }>(`/contabilidad/tipos-comprobante/${id}`);
    const tipo = data?.tipo ?? null;

    const handleSaved = () => {
        navigate('/contabilidad/tipos-comprobante', { replace: true, state: { message: 'Tipo de comprobante actualizado correctamente.' } });
    };

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Editar tipo de comprobante" />

            <div className="flex-1 rounded-2xl border border-gray-200 bg-white p-6 md:p-8 dark:border-gray-800 dark:bg-white/3">
                {error && (
                    <div className="mb-6 rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                        {error}
                    </div>
                )}

                {isLoading && (
                    <FormSkeletonBody />
                )}

                {tipo && <VoucherTypeForm voucherTypeId={tipo.id} initialData={tipo} onSaved={handleSaved} />}
            </div>
        </div>
    );
};
