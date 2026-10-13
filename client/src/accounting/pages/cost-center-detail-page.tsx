import type { FC } from 'react';
import { useParams } from 'react-router';

import { useResource } from '../../shared/hooks/use-resource';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { DetailSkeletonBody } from '@ui/skeleton/components/detail-skeleton-body';
import { CostCenterMovementsTable } from '../components/cost-center-movements-table';
import type { ApiCostCenter } from '../types/api-cost-center';

export const CostCenterDetailPage: FC = () => {
    const { id } = useParams<{ id: string }>();

    const { data, error, isLoading } = useResource<{ centro: ApiCostCenter }>(`/contabilidad/centros-costo/${id}`);
    const centro = data?.centro ?? null;

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle={centro ? `Centro de costo — ${centro.codigo}` : 'Centro de costo'} />

            <div className="flex-1 rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                {error && (
                    <div className="mb-6 rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                        {error}
                    </div>
                )}

                {isLoading && (
                    <DetailSkeletonBody />
                )}

                {centro && (
                    <>
                        <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div className="space-y-1 text-sm text-gray-500 dark:text-gray-400">
                                <p>Código: {centro.codigo}</p>
                                <p>Nombre: {centro.nombre}</p>
                            </div>

                            <span
                                className={`w-fit self-start rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                    centro.activo
                                        ? 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400'
                                        : 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400'
                                }`}
                            >
                                {centro.activo ? 'Activo' : 'Inactivo'}
                            </span>
                        </div>

                        <div className="grid grid-cols-1 gap-4 rounded-xl border border-gray-200 p-4 sm:grid-cols-2 dark:border-gray-800">
                            <div>
                                <p className="text-xs text-gray-400 dark:text-gray-500">Movimientos asociados</p>
                                <p className="font-semibold text-gray-800 dark:text-white/90">
                                    {centro.tiene_movimientos_asociados ? 'Sí' : 'No'}
                                </p>
                            </div>
                        </div>

                        <div className="mt-6">
                            <p className="mb-3 text-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">
                                Movimientos
                            </p>
                            <CostCenterMovementsTable centroId={centro.id} />
                        </div>
                    </>
                )}
            </div>
        </div>
    );
};
