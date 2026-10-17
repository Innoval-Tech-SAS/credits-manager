import type { FC } from 'react';

import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { NewImportForm } from '../components/new-import-form';

export const NewImportPage: FC = () => {
    return (
        <div className="flex flex-col gap-6">
            <PageBreadcrumb pageTitle="Nueva importación" />

            <NewImportForm />
        </div>
    );
};
