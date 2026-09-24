import type { FC } from 'react';
import { useState } from 'react';
import { useNavigate } from 'react-router';
import { FileText } from 'lucide-react';

import { useLocationSuccessMessage } from '../../shared/hooks/use-location-success-message';
import { useResource } from '../../shared/hooks/use-resource';
import { usePermission } from '../../auth/hooks/use-permission';
import { formatDateEs } from '../../shared/utils/format-date-es';
import { Button } from '@ui/button/components/button';
import { Label } from '@ui/forms/components/label';
import { Select } from '@ui/forms/components/select';
import { ModuleListPage } from '@ui/module/components/module-list-page';
import { DataTable } from '@ui/table/components/data-table';
import type { ParametrizationsListLocationState } from '../types/parametrizations-list-location-state';
import type { ApiExpenseType } from '../types/api-expense-type';
import type { ApiParametrization } from '../types/api-parametrization';

export const ParametrizationsPage: FC = () => {
    const navigate = useNavigate();

    const canCreate = usePermission('gastos', 'parametrizar');
    const [successMessage, setSuccessMessage] = useLocationSuccessMessage<ParametrizationsListLocationState>();
    const [tipoGastoId, setTipoGastoId] = useState('');

    const { data: tiposData } = useResource<{ tipos_gasto: ApiExpenseType[] }>('/gastos/tipos-gasto');
    const tiposGasto = tiposData?.tipos_gasto ?? [];
    const tipoGastoOptions = tiposGasto.map((tipo) => ({ value: String(tipo.id), label: `${tipo.codigo} - ${tipo.nombre}` }));

    const { data: parametrizacionesData, isLoading } = useResource<{ parametrizaciones: ApiParametrization[] }>(
        tipoGastoId ? `/gastos/parametrizaciones?tipo_gasto_id=${tipoGastoId}` : '',
    );
    const parametrizaciones = parametrizacionesData?.parametrizaciones ?? [];

    return (
        <ModuleListPage
            pageTitle="Parametrización de tipos de gasto"
            successMessage={successMessage}
            onDismissSuccess={() => setSuccessMessage(null)}
            countLabel={tipoGastoId ? `${parametrizaciones.length} versiones de vigencia` : 'Selecciona un tipo de gasto'}
            toolbar={
                <>
                    <div className="w-full sm:w-72">
                        <Label htmlFor="tipo-gasto-filtro">Tipo de gasto</Label>
                        <Select options={tipoGastoOptions} defaultValue={tipoGastoId} onChange={setTipoGastoId} />
                    </div>

                    {canCreate && (
                        <Button collapseBelow="md" startIcon={<FileText className="size-4" />} onClick={() => navigate('/gastos/parametrizaciones/crear')}>
                            Crear parametrización
                        </Button>
                    )}
                </>
            }
        >
            <DataTable
                columns={[
                    { key: 'fecha_desde', label: 'Vigente desde', render: (item) => formatDateEs(item.fecha_desde) },
                    { key: 'fecha_hasta', label: 'Vigente hasta', render: (item) => (item.fecha_hasta ? formatDateEs(item.fecha_hasta) : 'Vigente') },
                    { key: 'aplica_retefuente', label: 'ReteFuente', render: (item) => (item.aplica_retefuente ? 'Sí' : 'No') },
                    { key: 'aplica_reteica', label: 'ReteICA', render: (item) => (item.aplica_reteica ? 'Sí' : 'No') },
                    { key: 'aplica_reteiva', label: 'ReteIVA', render: (item) => (item.aplica_reteiva ? 'Sí' : 'No') },
                ]}
                rows={parametrizaciones}
                getRowKey={(row) => row.id}
                loading={isLoading}
                emptyMessage={tipoGastoId ? 'Este tipo de gasto no tiene parametrizaciones registradas.' : 'Selecciona un tipo de gasto para ver su historial.'}
            />
        </ModuleListPage>
    );
};
