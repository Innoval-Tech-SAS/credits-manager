import type { FC } from 'react';
import { useNavigate } from 'react-router';

import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { EconomicActivityForm } from '../components/economic-activity-form';

export const CreateEconomicActivityPage: FC = () => {
    const navigate = useNavigate();

    const handleCreated = () => {
        navigate('/gastos/actividades-economicas', { replace: true, state: { message: 'Actividad económica creada correctamente.' } });
    };

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Crear actividad económica" />

            <div className="flex-1 rounded-2xl border border-gray-200 bg-white p-6 md:p-8 dark:border-gray-800 dark:bg-white/3">
                <EconomicActivityForm onSaved={handleCreated} />
            </div>
        </div>
    );
};
