import type { FC } from 'react';
import { useState } from 'react';
import { useNavigate } from 'react-router';
import { MapPin, Pencil } from 'lucide-react';

import { useLocationSuccessMessage } from '../../shared/hooks/use-location-success-message';
import { usePaginatedResource } from '../../shared/hooks/use-paginated-resource';
import { ActionButton } from '@ui/action-button/components/action-button';
import { Button } from '@ui/button/components/button';
import { ModuleListPage } from '@ui/module/components/module-list-page';
import { DataTable } from '@ui/table/components/data-table';
import { Pagination } from '@ui/table/components/pagination';
import { EditMunicipalityModal } from '../components/edit-municipality-modal';
import { StateSelector } from '../components/state-selector';
import type { ApiMunicipality } from '../types/api-municipality';
import type { MunicipalitiesListLocationState } from '../types/municipalities-list-location-state';

export const MunicipalitiesAdminPage: FC = () => {
    const navigate = useNavigate();

    const [successMessage, setSuccessMessage] = useLocationSuccessMessage<MunicipalitiesListLocationState>();
    const [stateId, setStateId] = useState('');
    const [editing, setEditing] = useState<ApiMunicipality | null>(null);

    const { items, isLoading, page, setPage, totalPages, totalItems, refetch } = usePaginatedResource<ApiMunicipality>(
        stateId ? '/geografia/municipios/listado' : null,
        { estado_id: stateId },
    );
    const municipalities = stateId ? items : [];

    const handleStateChange = (id: string) => {
        setStateId(id);
        setPage(1);
    };

    const handleSaved = () => {
        setEditing(null);
        setSuccessMessage('Municipio actualizado correctamente.');
        refetch();
    };

    return (
        <ModuleListPage
            pageTitle="Municipios"
            successMessage={successMessage}
            onDismissSuccess={() => setSuccessMessage(null)}
            countLabel={
                stateId
                    ? `${totalItems} ${totalItems === 1 ? 'municipio' : 'municipios'} en el departamento`
                    : 'Elige un país y un departamento para ver sus municipios'
            }
            pagination={stateId ? <Pagination page={page} totalPages={totalPages} onPageChange={setPage} /> : undefined}
            toolbar={
                <Button collapseBelow="md" startIcon={<MapPin className="size-4" />} onClick={() => navigate('/geografia/municipios/crear')}>
                    Agregar municipio
                </Button>
            }
            modals={
                editing && <EditMunicipalityModal municipality={editing} onClose={() => setEditing(null)} onSaved={handleSaved} />
            }
        >
            <div className="mb-6 grid grid-cols-1 gap-6 md:grid-cols-3">
                <StateSelector onChange={handleStateChange} />
            </div>

            <DataTable
                columns={[
                    { key: 'codigo', label: 'Código DANE' },
                    {
                        key: 'nombre',
                        label: 'Municipio',
                        render: (municipality) => (
                            <span className="text-gray-800 dark:text-white/90">
                                {municipality.nombre}
                                {municipality.es_capital && <span className="ml-2 text-xs text-gray-500 dark:text-gray-400">Capital</span>}
                            </span>
                        ),
                    },
                    {
                        key: 'activo',
                        label: 'Estado',
                        render: (municipality) => (
                            <span
                                className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                    municipality.activo
                                        ? 'bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-400'
                                        : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'
                                }`}
                            >
                                {municipality.activo ? 'Activo' : 'Inactivo'}
                            </span>
                        ),
                    },
                    {
                        key: 'acciones',
                        label: 'Acciones',
                        render: (municipality) => (
                            <ActionButton icon={<Pencil className="size-5" />} label="Editar" onClick={() => setEditing(municipality)} />
                        ),
                    },
                ]}
                rows={municipalities}
                getRowKey={(row) => row.id}
                loading={isLoading}
                emptyMessage={stateId ? 'Este departamento no tiene municipios.' : 'Selecciona un departamento.'}
                maxHeightClassName="max-h-[65vh]"
            />
        </ModuleListPage>
    );
};
