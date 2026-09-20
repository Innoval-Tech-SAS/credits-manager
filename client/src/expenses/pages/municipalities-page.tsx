import type { FC } from 'react';
import { useNavigate } from 'react-router';
import { FileText } from 'lucide-react';

import { useLocationSuccessMessage } from '../../shared/hooks/use-location-success-message';
import { useResource } from '../../shared/hooks/use-resource';
import { usePermission } from '../../auth/hooks/use-permission';
import { Button } from '@ui/button/components/button';
import { ModuleListPage } from '@ui/module/components/module-list-page';
import { DataTable } from '@ui/table/components/data-table';
import type { MunicipalitiesListLocationState } from '../types/municipalities-list-location-state';
import type { ApiMunicipality } from '../types/api-municipality';

export const MunicipalitiesPage: FC = () => {
    const navigate = useNavigate();

    const canCreate = usePermission('gastos', 'parametrizar');
    const [successMessage, setSuccessMessage] = useLocationSuccessMessage<MunicipalitiesListLocationState>();
    const { data, isLoading } = useResource<{ municipios: ApiMunicipality[] }>('/gastos/municipios');
    const municipios = data?.municipios ?? [];

    return (
        <ModuleListPage
            pageTitle="Municipios"
            successMessage={successMessage}
            onDismissSuccess={() => setSuccessMessage(null)}
            countLabel={`${municipios.length} ${municipios.length === 1 ? 'municipio registrado' : 'municipios registrados'}`}
            toolbar={
                canCreate && (
                    <Button startIcon={<FileText className="size-4" />} onClick={() => navigate('/gastos/municipios/crear')}>
                        Crear municipio
                    </Button>
                )
            }
        >
            <DataTable
                columns={[
                    { key: 'codigo', label: 'Código' },
                    { key: 'nombre', label: 'Nombre' },
                    { key: 'activo', label: 'Estado', render: (municipio) => (municipio.activo ? 'Activo' : 'Inactivo') },
                ]}
                rows={municipios}
                getRowKey={(row) => row.id}
                loading={isLoading}
                emptyMessage="Sin municipios registrados."
            />
        </ModuleListPage>
    );
};
