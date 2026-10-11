import type { FC } from 'react';
import { useState } from 'react';

import { useResource } from '../../shared/hooks/use-resource';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { Spinner } from '@ui/spinner/components/spinner';
import { EmailSettingsForm } from '../components/email-settings-form';
import { EmailTypeTabs } from '../components/email-type-tabs';
import type { ApiEmailSettings } from '../types/api-email-settings';
import type { EmailType } from '../types/email-type';

export const EmailSettingsPage: FC = () => {
    const [tipo, setTipo] = useState<EmailType>('cobranza');

    const { data, error, refetch } = useResource<{ configuracion: ApiEmailSettings }>(`/correo/configuracion/${tipo}`);

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Correo" />

            <EmailTypeTabs tipo={tipo} onChange={setTipo} />

            <div className="flex-1 rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm md:p-8 dark:border-gray-800 dark:bg-gray-900">
                <div className="mb-8 flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h1 className="text-title-sm font-semibold text-gray-800 dark:text-white/90">
                            {tipo === 'cobranza' ? 'Correo de cobranza' : 'Correo de facturación'}
                        </h1>
                        <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            {tipo === 'cobranza'
                                ? 'Credenciales del servidor para los recordatorios que se envían a los clientes.'
                                : 'Credenciales del servidor para los correos con facturas, notas crédito y notas débito.'}
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

                {data && <EmailSettingsForm tipo={tipo} settings={data.configuracion} onSaved={refetch} />}
            </div>
        </div>
    );
};
