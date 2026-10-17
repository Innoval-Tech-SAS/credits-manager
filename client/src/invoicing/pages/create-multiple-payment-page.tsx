import type { FC } from 'react';

import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { CreateMultiplePaymentForm } from '../components/create-multiple-payment-form';

export const CreateMultiplePaymentPage: FC = () => {
    return (
        <div className="flex min-h-full flex-col gap-6 pb-6">
            <PageBreadcrumb pageTitle="Cobro múltiple" />

            <CreateMultiplePaymentForm />
        </div>
    );
};
