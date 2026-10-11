import type { FC } from 'react';
import { useState } from 'react';

import { useResource } from '../../shared/hooks/use-resource';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { Spinner } from '@ui/spinner/components/spinner';
import { EmailSettingsForm } from '../components/email-settings-form';
import { EmailTemplateList } from '../components/email-template-list';
import type { ApiEmailSettings } from '../types/api-email-settings';
import type { ApiPlantillaCorreo } from '../types/api-plantilla-correo';
import type { EmailType } from '../types/email-type';

const PESTANAS: { value: EmailType; label: string }[] = [
    { value: 'cobranza', label: 'Cobranza' },
    { value: 'facturacion', label: 'Facturación' },
];

export const EmailSettingsPage: FC = () => {
    const [tipo, setTipo] = useState<EmailType>('cobranza');

    const { data, error, refetch } = useResource<{ configuracion: ApiEmailSettings }>(`/correo/configuracion/${tipo}`);
    const { data: plantillasData, refetch: refetchPlantillas } = useResource<{ plantillas: ApiPlantillaCorreo[] }>(`/correo/plantillas?tipo=${tipo}`);

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Correo" />

            <div className="mb-6 inline-flex w-fit rounded-lg border border-gray-200 p-1 dark:border-gray-800" role="tablist">
                {PESTANAS.map((pestana) => (
                    <button
                        key={pestana.value}
                        type="button"
                        role="tab"
                        aria-selected={tipo === pestana.value}
                        onClick={() => setTipo(pestana.value)}
                        className={`rounded-md px-4 py-2 text-sm font-medium transition ${
                            tipo === pestana.value
                                ? 'bg-brand-500 text-white'
                                : 'text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white/90'
                        }`}
                    >
                        {pestana.label}
                    </button>
                ))}
            </div>

            <div className="flex-1 rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm md:p-8 dark:border-gray-800 dark:bg-gray-900">
                <div className="mb-8 flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h1 className="text-title-sm font-semibold text-gray-800 dark:text-white/90">
                            {tipo === 'cobranza' ? 'Correo de cobranza' : 'Correo de facturación'}
                        </h1>
                        <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            {tipo === 'cobranza'
                                ? 'Credenciales del servidor y plantillas de los recordatorios que se envían a los clientes.'
                                : 'Credenciales del servidor y plantillas de los correos con facturas, notas crédito y notas débito.'}
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

                {data && (
                    <div className="flex flex-col gap-8">
                        <EmailSettingsForm tipo={tipo} settings={data.configuracion} onSaved={refetch} />

                        <div className="border-t border-gray-200 pt-8 dark:border-gray-800">
                            <EmailTemplateList tipo={tipo} plantillas={plantillasData?.plantillas ?? []} onChanged={refetchPlantillas} />
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
};
