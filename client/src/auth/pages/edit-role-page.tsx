import type { FC } from 'react';
import { useParams } from 'react-router';

import { useResource } from '../../shared/hooks/use-resource';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { Spinner } from '@ui/spinner/components/spinner';
import { RoleForm } from '../components/role-form';
import type { ApiRoleWithPermisos } from '../types/api-role';

export const EditRolePage: FC = () => {
    const { id } = useParams<{ id: string }>();

    const { data, error } = useResource<{ rol: ApiRoleWithPermisos }>(`/roles/${id}`);
    const rol = data?.rol ?? null;

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Editar rol" />

            <div className="flex-1 rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                <div className="mb-6">
                    <p className="text-sm text-gray-500 dark:text-gray-400">Actualiza el nombre y los permisos base de este rol.</p>
                </div>

                <div className="mx-auto max-w-3xl xl:max-w-6xl">
                    {error && (
                        <div className="rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                            {error}
                        </div>
                    )}

                    {!error && !rol && (
                        <div className="flex justify-center py-12">
                            <Spinner size="lg" />
                        </div>
                    )}

                    {rol && <RoleForm roleId={rol.id} initialData={rol} />}
                </div>
            </div>
        </div>
    );
};
