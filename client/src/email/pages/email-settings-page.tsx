import type { FC } from 'react';

import { useResource } from '../../shared/hooks/use-resource';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { Spinner } from '@ui/spinner/components/spinner';
import { EmailSettingsForm } from '../components/email-settings-form';
import type { ApiEmailSettings } from '../types/api-email-settings';

export const EmailSettingsPage: FC = () => {
    const { data, error, refetch } = useResource<{ configuracion: ApiEmailSettings }>('/correo/configuracion');

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Correo de cobranza" />

            <div className="flex-1 rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm md:p-8 dark:border-gray-800 dark:bg-gray-900">
                <div className="mb-8 flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h1 className="text-title-sm font-semibold text-gray-800 dark:text-white/90">Correo de cobranza</h1>
                        <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            Credenciales del servidor de correo y plantilla de los recordatorios que se envían a los clientes.
                        </p>
                    </div>
                    {data && (
                        <span
                            className={
                                data.configuracion.estado === 'configurada'
                                    ? 'inline-flex w-fit rounded-full bg-success-50 px-3 py-1 text-xs font-medium text-success-600 dark:bg-success-500/15 dark:text-success-500'
                                    : 'inline-flex w-fit rounded-full bg-warning-50 px-3 py-1 text-xs font-medium text-warning-600 dark:bg-warning-500/15 dark:text-orange-400'
                            }
                        >
                            {data.configuracion.estado === 'configurada' ? 'Configurada' : 'Sin configurar'}
                        </span>
                    )}
                </div>

                {error && (
                    <div className="rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                        {error}
                    </div>
                )}

                {!data && !error && (
                    <div className="flex justify-center py-12">
                        <Spinner size="lg" />
                    </div>
                )}

                {data && <EmailSettingsForm settings={data.configuracion} onSaved={refetch} />}
            </div>
        </div>
    );
};
