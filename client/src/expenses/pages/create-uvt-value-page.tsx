import type { FC } from 'react';
import { useNavigate } from 'react-router';

import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { UvtValueForm } from '../components/uvt-value-form';

export const CreateUvtValuePage: FC = () => {
    const navigate = useNavigate();

    const handleCreated = () => {
        navigate('/gastos/valores-uvt', { replace: true, state: { message: 'Valor UVT guardado correctamente.' } });
    };

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Guardar valor UVT" />

            <div className="flex-1 rounded-2xl border border-gray-200 bg-white p-6 md:p-8 dark:border-gray-800 dark:bg-white/3">
                <UvtValueForm onCreated={handleCreated} />
            </div>
        </div>
    );
};
