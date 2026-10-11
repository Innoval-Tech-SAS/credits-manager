import type { FC } from 'react';
import { useParams } from 'react-router';

import { useResource } from '../../shared/hooks/use-resource';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { Spinner } from '@ui/spinner/components/spinner';
import { EmailTemplateForm } from '../components/email-template-form';
import type { ApiPlantillaCorreo } from '../types/api-plantilla-correo';
import type { EmailType } from '../types/email-type';

export const EditEmailTemplatePage: FC = () => {
    const { tipo, id } = useParams<{ tipo: EmailType; id: string }>();

    const { data, error } = useResource<{ plantillas: ApiPlantillaCorreo[] }>(`/correo/plantillas?tipo=${tipo}`);
    const plantilla = data?.plantillas.find((fila) => fila.id === Number(id)) ?? null;

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Editar plantilla" />

            <div className="flex-1 rounded-2xl border border-gray-200 bg-white p-6 md:p-8 dark:border-gray-800 dark:bg-white/3">
                {error && (
                    <div className="mb-6 rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                        {error}
                    </div>
                )}

                {!error && !plantilla && (
                    <div className="flex justify-center py-12">
                        <Spinner size="lg" />
                    </div>
                )}

                {plantilla && <EmailTemplateForm tipo={tipo as EmailType} templateId={plantilla.id} initialData={plantilla} />}
            </div>
        </div>
    );
};
