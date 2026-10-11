import type { FC } from 'react';
import { useParams } from 'react-router';

import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { EmailTemplateForm } from '../components/email-template-form';
import type { EmailType } from '../types/email-type';

export const CreateEmailTemplatePage: FC = () => {
    const { tipo } = useParams<{ tipo: EmailType }>();

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Crear plantilla" />

            <div className="flex-1 rounded-2xl border border-gray-200 bg-white p-6 md:p-8 dark:border-gray-800 dark:bg-white/3">
                <EmailTemplateForm tipo={tipo as EmailType} />
            </div>
        </div>
    );
};
