import type { FC } from 'react';
import { useParams } from 'react-router';

import { useResource } from '../../shared/hooks/use-resource';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { Spinner } from '@ui/spinner/components/spinner';
import type { ApiVoucherType } from '../types/api-voucher-type';

export const VoucherTypeDetailPage: FC = () => {
    const { id } = useParams<{ id: string }>();

    const { data, error, isLoading } = useResource<{ tipo: ApiVoucherType }>(`/contabilidad/tipos-comprobante/${id}`);
    const tipo = data?.tipo ?? null;

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle={tipo ? `Tipo de comprobante — ${tipo.codigo}` : 'Tipo de comprobante'} />

            <div className="flex-1 rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                {error && (
                    <div className="mb-6 rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                        {error}
                    </div>
                )}

                {isLoading && (
                    <div className="flex justify-center py-12">
                        <Spinner size="lg" />
                    </div>
                )}

                {tipo && (
                    <div className="mb-2 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div className="space-y-1 text-sm text-gray-500 dark:text-gray-400">
                            <p>Código: {tipo.codigo}</p>
                            <p>Nombre: {tipo.nombre}</p>
                            <p>Consecutivo actual: {tipo.consecutivo_actual}</p>
                        </div>

                        <span
                            className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                tipo.activo
                                    ? 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400'
                                    : 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400'
                            }`}
                        >
                            {tipo.activo ? 'Activo' : 'Inactivo'}
                        </span>
                    </div>
                )}
            </div>
        </div>
    );
};
