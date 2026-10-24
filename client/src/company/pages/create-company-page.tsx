import type { FC } from 'react';

import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { CreateCompanyForm } from '../components/create-company-form';

export const CreateCompanyPage: FC = () => {
    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Crear empresa" />

            <div className="flex-1 rounded-2xl border border-gray-200 bg-white p-6 md:p-8 dark:border-gray-800 dark:bg-white/3">
                <CreateCompanyForm />
            </div>
        </div>
    );
};
