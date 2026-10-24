import type { FC } from 'react';

import { RoleForm } from '../components/role-form';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';

export const CreateRolePage: FC = () => {
    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Crear rol" />

            <div className="flex-1 rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                <div className="mb-6">
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        Define el nombre y los permisos base que tendrán los usuarios con este rol.
                    </p>
                </div>

                <div className="mx-auto max-w-3xl xl:max-w-6xl">
                    <RoleForm />
                </div>
            </div>
        </div>
    );
};
