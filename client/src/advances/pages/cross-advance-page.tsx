import type { FC } from 'react';

import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { CrossAdvanceForm } from '../components/cross-advance-form';

export const CrossAdvancePage: FC = () => {
    return (
        <div className="flex min-h-full flex-col gap-6 pb-6">
            <PageBreadcrumb pageTitle="Cruzar anticipo" />

            <CrossAdvanceForm />
        </div>
    );
};
