import type { FC } from 'react';
import { useNavigate } from 'react-router';

import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { MunicipalityForm } from '../components/municipality-form';

export const CreateMunicipalityPage: FC = () => {
    const navigate = useNavigate();

    const handleCreated = () => {
        navigate('/geografia/municipios', { replace: true, state: { message: 'Municipio creado correctamente.' } });
    };

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Agregar municipio" />

            <div className="flex-1 rounded-2xl border border-gray-200 bg-white p-6 md:p-8 dark:border-gray-800 dark:bg-white/3">
                <MunicipalityForm onCreated={handleCreated} />
            </div>
        </div>
    );
};
