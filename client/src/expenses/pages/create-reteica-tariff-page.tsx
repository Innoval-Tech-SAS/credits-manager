import type { FC } from 'react';
import { useNavigate } from 'react-router';

import { useResource } from '../../shared/hooks/use-resource';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { FormSkeletonBody } from '@ui/skeleton/components/form-skeleton-body';
import { ReteicaTariffForm } from '../components/reteica-tariff-form';
import type { ApiEconomicActivity } from '../types/api-economic-activity';

export const CreateReteicaTariffPage: FC = () => {
    const navigate = useNavigate();

    const { data: actividadesData, isLoading } = useResource<{ actividades_economicas: ApiEconomicActivity[] }>('/gastos/actividades-economicas');

    const actividades = actividadesData?.actividades_economicas ?? [];

    const handleCreated = () => {
        navigate('/gastos/tarifas-reteica', { replace: true, state: { message: 'Tarifa de ReteICA creada correctamente.' } });
    };

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Crear tarifa de ReteICA" />

            <div className="flex-1 rounded-2xl border border-gray-200 bg-white p-6 md:p-8 dark:border-gray-800 dark:bg-white/3">
                {isLoading ? (
                    <FormSkeletonBody />
                ) : (
                    <ReteicaTariffForm actividades={actividades} onCreated={handleCreated} />
                )}
            </div>
        </div>
    );
};
