import type { FC } from 'react';
import { isRouteErrorResponse, useNavigate, useRouteError } from 'react-router';

import { Button } from '@ui/button/components/button';

export const ErrorPage: FC = () => {
    const error = useRouteError();
    const navigate = useNavigate();

    const mensaje = isRouteErrorResponse(error)
        ? error.statusText || 'No se pudo completar la solicitud.'
        : error instanceof Error
          ? error.message
          : 'Ocurrió un error inesperado.';

    return (
        <div className="flex min-h-screen items-center justify-center bg-gray-50 px-4 dark:bg-gray-900">
            <div className="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-8 text-center shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
                <h1 className="text-title-sm font-semibold text-gray-800 dark:text-white/90">Algo salió mal</h1>
                <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">{mensaje}</p>
                <div className="mt-6 flex justify-center gap-3">
                    <Button variant="outline" onClick={() => navigate(-1)}>
                        Volver
                    </Button>
                    <Button onClick={() => navigate('/', { replace: true })}>Ir al inicio</Button>
                </div>
            </div>
        </div>
    );
};
