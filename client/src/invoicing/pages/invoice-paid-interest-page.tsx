import type { FC } from 'react';

import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { InvoicePaidInterestForm } from '../components/invoice-paid-interest-form';

export const InvoicePaidInterestPage: FC = () => {
    return (
        <div className="flex min-h-full flex-col gap-6 pb-6">
            <PageBreadcrumb pageTitle="Facturar intereses pagados" />

            <InvoicePaidInterestForm />
        </div>
    );
};
