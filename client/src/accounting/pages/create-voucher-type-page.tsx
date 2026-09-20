import type { FC } from 'react';
import { useNavigate } from 'react-router';

import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { VoucherTypeForm } from '../components/voucher-type-form';

export const CreateVoucherTypePage: FC = () => {
    const navigate = useNavigate();

    const handleCreated = () => {
        navigate('/contabilidad/tipos-comprobante', { replace: true, state: { message: 'Tipo de comprobante creado correctamente.' } });
    };

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Crear tipo de comprobante" />

            <div className="flex-1 rounded-2xl border border-gray-200 bg-white p-6 md:p-8 dark:border-gray-800 dark:bg-white/3">
                <VoucherTypeForm onCreated={handleCreated} />
            </div>
        </div>
    );
};
