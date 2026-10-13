import type { FC } from 'react';
import { useNavigate } from 'react-router';
import { FileText } from 'lucide-react';

import { useLocationSuccessMessage } from '../../shared/hooks/use-location-success-message';
import { useResource } from '../../shared/hooks/use-resource';
import { usePermission } from '../../auth/hooks/use-permission';
import { CreateButton } from '@ui/button/components/create-button';
import { formatDateEs } from '../../shared/utils/format-date-es';
import { ModuleListPage } from '@ui/module/components/module-list-page';
import { DataTable } from '@ui/table/components/data-table';
import type { ReteicaTariffsListLocationState } from '../types/reteica-tariffs-list-location-state';
import type { ApiReteicaTariff } from '../types/api-reteica-tariff';

export const ReteicaTariffsPage: FC = () => {
    const navigate = useNavigate();

    const canCreate = usePermission('tributario', 'crear_tarifas_reteica');
    const [successMessage, setSuccessMessage] = useLocationSuccessMessage<ReteicaTariffsListLocationState>();
    const { data, isLoading } = useResource<{ tarifas: ApiReteicaTariff[] }>('/gastos/tarifas-reteica');
    const tarifas = data?.tarifas ?? [];

    return (
        <ModuleListPage
            pageTitle="Tarifas de ReteICA"
            successMessage={successMessage}
            onDismissSuccess={() => setSuccessMessage(null)}
            countLabel={`${tarifas.length} ${tarifas.length === 1 ? 'tarifa registrada' : 'tarifas registradas'}`}
            toolbar={
                canCreate && <CreateButton label="tarifa" icon={<FileText className="size-4" />} onClick={() => navigate('/gastos/tarifas-reteica/crear')} />
            }
        >
            <DataTable
                columns={[
                    { key: 'municipio', label: 'Municipio', render: (tarifa) => tarifa.municipio?.nombre ?? '—' },
                    { key: 'actividad_economica', label: 'Actividad económica', render: (tarifa) => tarifa.actividad_economica?.nombre ?? '—' },
                    { key: 'fecha_desde', label: 'Vigente desde', render: (tarifa) => formatDateEs(tarifa.fecha_desde) },
                    { key: 'fecha_hasta', label: 'Vigente hasta', render: (tarifa) => (tarifa.fecha_hasta ? formatDateEs(tarifa.fecha_hasta) : 'Vigente') },
                    { key: 'tarifa', label: 'Tarifa (por mil)' },
                ]}
                rows={tarifas}
                getRowKey={(row) => row.id}
                loading={isLoading}
                emptyMessage="Sin tarifas de ReteICA registradas."
                maxHeightClassName="max-h-[65vh]"
            />
        </ModuleListPage>
    );
};
