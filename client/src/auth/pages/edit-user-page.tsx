import type { FC } from 'react';
import { useParams } from 'react-router';

import { useResource } from '../../shared/hooks/use-resource';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { Spinner } from '@ui/spinner/components/spinner';
import { RegisterForm } from '../components/register-form';
import type { ApiUserDetail } from '../types/api-user-detail';

export const EditUserPage: FC = () => {
    const { id } = useParams<{ id: string }>();

    const { data, error } = useResource<{ usuario: ApiUserDetail }>(`/usuarios/${id}`);
    const usuario = data?.usuario ?? null;

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Editar usuario" />

            <div className="flex-1 rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                <div className="mb-6">
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        Actualiza los datos del usuario y los módulos a los que tiene acceso.
                    </p>
                </div>

                <div className="mx-auto max-w-3xl xl:max-w-6xl">
                    {error && (
                        <div className="rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                            {error}
                        </div>
                    )}

                    {!error && !usuario && (
                        <div className="flex justify-center py-12">
                            <Spinner size="lg" />
                        </div>
                    )}

                    {usuario && (
                        <RegisterForm userId={usuario.id} initialData={usuario} />
                    )}
                </div>
            </div>
        </div>
    );
};
