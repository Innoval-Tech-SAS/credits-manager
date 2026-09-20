import type { FC } from 'react';
import { useNavigate } from 'react-router';

import { useResource } from '../../shared/hooks/use-resource';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { Spinner } from '@ui/spinner/components/spinner';
import { ReteicaTariffForm } from '../components/reteica-tariff-form';
import type { ApiEconomicActivity } from '../types/api-economic-activity';
import type { ApiMunicipality } from '../types/api-municipality';

export const CreateReteicaTariffPage: FC = () => {
    const navigate = useNavigate();

    const { data: municipiosData, isLoading: isLoadingMunicipios } = useResource<{ municipios: ApiMunicipality[] }>('/gastos/municipios');
    const { data: actividadesData, isLoading: isLoadingActividades } = useResource<{ actividades_economicas: ApiEconomicActivity[] }>(
        '/gastos/actividades-economicas',
    );

    const municipios = municipiosData?.municipios ?? [];
    const actividades = actividadesData?.actividades_economicas ?? [];

    const handleCreated = () => {
        navigate('/gastos/tarifas-reteica', { replace: true, state: { message: 'Tarifa de ReteICA creada correctamente.' } });
    };

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Crear tarifa de ReteICA" />

            <div className="flex-1 rounded-2xl border border-gray-200 bg-white p-6 md:p-8 dark:border-gray-800 dark:bg-white/3">
                {isLoadingMunicipios || isLoadingActividades ? (
                    <div className="flex justify-center py-12">
                        <Spinner size="lg" />
                    </div>
                ) : (
                    <ReteicaTariffForm municipios={municipios} actividades={actividades} onCreated={handleCreated} />
                )}
            </div>
        </div>
    );
};
