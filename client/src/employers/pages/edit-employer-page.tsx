import type { FC } from 'react';
import { useParams } from 'react-router';

import { useResource } from '../../shared/hooks/use-resource';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { FormSkeletonBody } from '@ui/skeleton/components/form-skeleton-body';
import { EmployerForm } from '../components/employer-form';
import type { ApiEmployer } from '../types/api-employer';

export const EditEmployerPage: FC = () => {
    const { id } = useParams<{ id: string }>();

    const { data, error } = useResource<{ empleador: ApiEmployer }>(`/empleadores/${id}`);
    const empleador = data?.empleador ?? null;

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Editar empleador" />

            <div className="flex-1 rounded-2xl border border-gray-200 bg-white p-6 md:p-8 dark:border-gray-800 dark:bg-white/3">
                {error && (
                    <div className="mb-6 rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                        {error}
                    </div>
                )}

                {!error && !empleador && (
                    <FormSkeletonBody />
                )}

                {empleador && <EmployerForm employerId={empleador.id} initialData={empleador} />}
            </div>
        </div>
    );
};
