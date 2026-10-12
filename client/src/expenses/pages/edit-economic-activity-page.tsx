import type { FC } from 'react';
import { useNavigate, useParams } from 'react-router';

import { useResource } from '../../shared/hooks/use-resource';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { FormSkeletonBody } from '@ui/skeleton/components/form-skeleton-body';
import { EconomicActivityForm } from '../components/economic-activity-form';
import type { ApiEconomicActivity } from '../types/api-economic-activity';

/** Sin endpoint de detalle: la actividad sale del mismo catálogo que lista la pantalla. */
export const EditEconomicActivityPage: FC = () => {
    const navigate = useNavigate();
    const { id } = useParams<{ id: string }>();

    const { data, error, isLoading } = useResource<{ actividades_economicas: ApiEconomicActivity[] }>('/gastos/actividades-economicas');
    const actividad = data?.actividades_economicas.find((item) => String(item.id) === id) ?? null;

    const handleSaved = () => {
        navigate('/gastos/actividades-economicas', { replace: true, state: { message: 'Actividad económica actualizada correctamente.' } });
    };

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Editar actividad económica" />

            <div className="flex-1 rounded-2xl border border-gray-200 bg-white p-6 md:p-8 dark:border-gray-800 dark:bg-white/3">
                {error && (
                    <div className="mb-6 rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                        {error}
                    </div>
                )}

                {isLoading && (
                    <FormSkeletonBody />
                )}

                {data && !actividad && <p className="text-sm text-gray-500 dark:text-gray-400">La actividad económica no existe.</p>}

                {actividad && <EconomicActivityForm activityId={actividad.id} initialData={actividad} onSaved={handleSaved} />}
            </div>
        </div>
    );
};
