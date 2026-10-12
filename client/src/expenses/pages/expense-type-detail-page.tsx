import type { FC } from 'react';
import { useParams } from 'react-router';

import { useResource } from '../../shared/hooks/use-resource';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { DetailSkeletonBody } from '@ui/skeleton/components/detail-skeleton-body';
import type { ApiExpenseType } from '../types/api-expense-type';

export const ExpenseTypeDetailPage: FC = () => {
    const { id } = useParams<{ id: string }>();

    const { data, error, isLoading } = useResource<{ tipo_gasto: ApiExpenseType }>(`/gastos/tipos-gasto/${id}`);
    const tipoGasto = data?.tipo_gasto ?? null;

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle={tipoGasto ? `Tipo de gasto — ${tipoGasto.codigo}` : 'Tipo de gasto'} />

            <div className="flex-1 rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                {error && (
                    <div className="mb-6 rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                        {error}
                    </div>
                )}

                {isLoading && (
                    <DetailSkeletonBody />
                )}

                {tipoGasto && (
                    <>
                        <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div className="space-y-1 text-sm text-gray-500 dark:text-gray-400">
                                <p>Código: {tipoGasto.codigo}</p>
                                <p>Nombre: {tipoGasto.nombre}</p>
                            </div>

                            <span
                                className={`w-fit self-start rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                    tipoGasto.activo
                                        ? 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400'
                                        : 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400'
                                }`}
                            >
                                {tipoGasto.activo ? 'Activo' : 'Inactivo'}
                            </span>
                        </div>

                        <div className="grid grid-cols-2 gap-4 rounded-xl border border-gray-200 p-4 sm:grid-cols-3 dark:border-gray-800">
                            <div>
                                <p className="text-xs text-gray-400 dark:text-gray-500">Requiere tercero</p>
                                <p className="font-semibold text-gray-800 dark:text-white/90">{tipoGasto.requiere_tercero ? 'Sí' : 'No'}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-400 dark:text-gray-500">Aplica IVA</p>
                                <p className="font-semibold text-gray-800 dark:text-white/90">{tipoGasto.aplica_iva ? 'Sí' : 'No'}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-400 dark:text-gray-500">Gastos asociados</p>
                                <p className="font-semibold text-gray-800 dark:text-white/90">
                                    {tipoGasto.tiene_gastos_asociados ? 'Sí' : 'No'}
                                </p>
                            </div>
                        </div>
                    </>
                )}
            </div>
        </div>
    );
};
