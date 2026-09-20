import type { FC } from 'react';

import { LoginForm } from '../components/login-form';

export const LoginPage: FC = () => {
    return (
        <div className="flex min-h-screen items-center justify-center bg-gray-50 px-4 dark:bg-gray-900">
            <div className="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-8 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
                <div className="mb-8 text-center">
                    <h1 className="text-title-sm font-semibold text-gray-800 dark:text-white/90">Sistema de Créditos</h1>
                    <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Ingresa con tu usuario y contraseña
                    </p>
                </div>

                <LoginForm />
            </div>
        </div>
    );
};
