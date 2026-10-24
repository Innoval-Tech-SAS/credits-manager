import type { FC } from 'react';

import { RegisterForm } from '../components/register-form';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';

export const CreateUserPage: FC = () => {
    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Crear usuario" />

            <div className="flex-1 rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                <div className="mb-6">
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        Crea asesores o administradores y define a qué módulos tiene acceso cada asesor.
                    </p>
                </div>

                <div className="mx-auto max-w-3xl xl:max-w-6xl">
                    <RegisterForm />
                </div>
            </div>
        </div>
    );
};
