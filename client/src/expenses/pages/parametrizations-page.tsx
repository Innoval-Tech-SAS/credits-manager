import type { FC } from 'react';
import { useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router';
import { FileText, Pencil } from 'lucide-react';

import { useLocationSuccessMessage } from '../../shared/hooks/use-location-success-message';
import { useResource } from '../../shared/hooks/use-resource';
import { usePermission } from '../../auth/hooks/use-permission';
import { formatDateEs } from '../../shared/utils/format-date-es';
import { ActionButton } from '@ui/action-button/components/action-button';
import { CreateButton } from '@ui/button/components/create-button';
import { Label } from '@ui/forms/components/label';
import { Select } from '@ui/forms/components/select';
import { ModuleListPage } from '@ui/module/components/module-list-page';
import { DataTable } from '@ui/table/components/data-table';
import type { ParametrizationsListLocationState } from '../types/parametrizations-list-location-state';
import type { ApiExpenseType } from '../types/api-expense-type';
import type { ApiParametrization } from '../types/api-parametrization';

export const ParametrizationsPage: FC = () => {
    const navigate = useNavigate();

    const canCreate = usePermission('gastos', 'crear_parametrizaciones');
    const [successMessage, setSuccessMessage] = useLocationSuccessMessage<ParametrizationsListLocationState>();
    const [searchParams] = useSearchParams();
    // Al volver de editar una versión, el historial abre en el mismo tipo de gasto.
    const [tipoGastoId, setTipoGastoId] = useState(() => searchParams.get('tipo_gasto_id') ?? '');

    const { data: tiposData } = useResource<{ tipos_gasto: ApiExpenseType[] }>('/gastos/tipos-gasto');
    const tiposGasto = tiposData?.tipos_gasto ?? [];
    const tipoGastoOptions = tiposGasto.map((tipo) => ({ value: String(tipo.id), label: `${tipo.codigo} - ${tipo.nombre}` }));

    const { data: parametrizacionesData, isLoading } = useResource<{ parametrizaciones: ApiParametrization[] }>(
        tipoGastoId ? `/gastos/parametrizaciones?tipo_gasto_id=${tipoGastoId}` : null,
    );
    const parametrizaciones = parametrizacionesData?.parametrizaciones ?? [];

    return (
        <ModuleListPage
            pageTitle="Parametrización de tipos de gasto"
            successMessage={successMessage}
            onDismissSuccess={() => setSuccessMessage(null)}
            countLabel={
                tipoGastoId
                    ? `${parametrizaciones.length} ${parametrizaciones.length === 1 ? 'versión de vigencia' : 'versiones de vigencia'}`
                    : 'Selecciona un tipo de gasto'
            }
            toolbar={
                <>
                    <div className="w-full sm:w-72">
                        <Label htmlFor="tipo-gasto-filtro">Tipo de gasto</Label>
                        <Select id="tipo-gasto-filtro" options={tipoGastoOptions} defaultValue={tipoGastoId} onChange={setTipoGastoId} />
                    </div>

                    {canCreate && (
                        <CreateButton label="parametrización" icon={<FileText className="size-4" />} onClick={() => navigate('/gastos/parametrizaciones/crear')} />
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
                    ...(canCreate
                        ? [
                              {
                                  key: 'acciones',
                                  label: 'Acciones',
                                  render: (item: ApiParametrization) => (
                                      <ActionButton
                                          icon={<Pencil className="size-5" />}
                                          label="Editar"
                                          onClick={() => navigate(`/gastos/parametrizaciones/${item.id}/editar`)}
                                      />
                                  ),
                              },
                          ]
                        : []),
                ]}
                rows={parametrizaciones}
                getRowKey={(row) => row.id}
                loading={isLoading}
                emptyMessage={tipoGastoId ? 'Este tipo de gasto no tiene parametrizaciones registradas.' : 'Selecciona un tipo de gasto para ver su historial.'}
                maxHeightClassName="max-h-[65vh]"
            />
        </ModuleListPage>
    );
};
