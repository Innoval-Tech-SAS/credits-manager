import type { FC } from 'react';
import { useNavigate } from 'react-router';
import { FileText } from 'lucide-react';

import { useLocationSuccessMessage } from '../../shared/hooks/use-location-success-message';
import { useResource } from '../../shared/hooks/use-resource';
import { usePermission } from '../../auth/hooks/use-permission';
import { Button } from '@ui/button/components/button';
import { ModuleListPage } from '@ui/module/components/module-list-page';
import { DataTable } from '@ui/table/components/data-table';
import type { EconomicActivitiesListLocationState } from '../types/economic-activities-list-location-state';
import type { ApiEconomicActivity } from '../types/api-economic-activity';

export const EconomicActivitiesPage: FC = () => {
    const navigate = useNavigate();

    const canCreate = usePermission('tributario', 'crear_actividades_economicas');
    const [successMessage, setSuccessMessage] = useLocationSuccessMessage<EconomicActivitiesListLocationState>();
    const { data, isLoading } = useResource<{ actividades_economicas: ApiEconomicActivity[] }>('/gastos/actividades-economicas');
    const actividades = data?.actividades_economicas ?? [];

    return (
        <ModuleListPage
            pageTitle="Actividades económicas"
            successMessage={successMessage}
            onDismissSuccess={() => setSuccessMessage(null)}
            countLabel={`${actividades.length} ${actividades.length === 1 ? 'actividad registrada' : 'actividades registradas'}`}
            toolbar={
                canCreate && (
                    <Button collapseBelow="md" startIcon={<FileText className="size-4" />} onClick={() => navigate('/gastos/actividades-economicas/crear')}>
                        Crear actividad económica
                    </Button>
                )
            }
        >
            <DataTable
                columns={[
                    { key: 'codigo', label: 'Código' },
                    { key: 'nombre', label: 'Nombre' },
                    { key: 'activo', label: 'Estado', render: (actividad) => (actividad.activo ? 'Activo' : 'Inactivo') },
                ]}
                rows={actividades}
                getRowKey={(row) => row.id}
                loading={isLoading}
                emptyMessage="Sin actividades económicas registradas."
                maxHeightClassName="max-h-[65vh]"
            />
        </ModuleListPage>
    );
};
