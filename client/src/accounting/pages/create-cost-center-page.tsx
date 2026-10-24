import type { FC } from 'react';
import { useNavigate } from 'react-router';

import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { CostCenterForm } from '../components/cost-center-form';

export const CreateCostCenterPage: FC = () => {
    const navigate = useNavigate();

    const handleSaved = () => {
        navigate('/contabilidad/centros-costo', { replace: true, state: { message: 'Centro de costo creado correctamente.' } });
    };

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Crear centro de costo" />

            <div className="flex-1 rounded-2xl border border-gray-200 bg-white p-6 md:p-8 dark:border-gray-800 dark:bg-white/3">
                <CostCenterForm onSaved={handleSaved} />
            </div>
        </div>
    );
};
