import type { FC } from 'react';

import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { CreateAdvanceForm } from '../components/create-advance-form';

export const CreateAdvancePage: FC = () => {
    return (
        <div className="flex min-h-full flex-col gap-6 pb-6">
            <PageBreadcrumb pageTitle="Registrar anticipo" />

            <CreateAdvanceForm />
        </div>
    );
};
