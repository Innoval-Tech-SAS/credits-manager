import type { FC } from 'react';

import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { EditCompanyForm } from '../components/edit-company-form';

export const EditCompanyPage: FC = () => {
    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Empresa" />

            <div className="flex-1 rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm md:p-8 dark:border-gray-800 dark:bg-gray-900">
                <EditCompanyForm />
            </div>
        </div>
    );
};
