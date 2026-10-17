import type { FC } from 'react';

import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { CreateInvoiceForm } from '../components/create-invoice-form';

export const CreateInvoicePage: FC = () => {
    return (
        <div className="flex min-h-full flex-col gap-6 pb-6">
            <PageBreadcrumb pageTitle="Nueva factura" />

            <CreateInvoiceForm />
        </div>
    );
};
