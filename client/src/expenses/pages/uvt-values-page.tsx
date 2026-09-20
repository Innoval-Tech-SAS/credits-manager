import type { FC } from 'react';
import { useNavigate } from 'react-router';
import { FileText } from 'lucide-react';

import { useLocationSuccessMessage } from '../../shared/hooks/use-location-success-message';
import { useResource } from '../../shared/hooks/use-resource';
import { usePermission } from '../../auth/hooks/use-permission';
import { Button } from '@ui/button/components/button';
import { ModuleListPage } from '@ui/module/components/module-list-page';
import { DataTable } from '@ui/table/components/data-table';
import type { UvtValuesListLocationState } from '../types/uvt-values-list-location-state';
import type { ApiUvtValue } from '../types/api-uvt-value';

export const UvtValuesPage: FC = () => {
    const navigate = useNavigate();

    const canCreate = usePermission('gastos', 'parametrizar');
    const [successMessage, setSuccessMessage] = useLocationSuccessMessage<UvtValuesListLocationState>();
    const { data, isLoading } = useResource<{ valores_uvt: ApiUvtValue[] }>('/gastos/valores-uvt');
    const valoresUvt = data?.valores_uvt ?? [];

    return (
        <ModuleListPage
            pageTitle="Valores UVT"
            successMessage={successMessage}
            onDismissSuccess={() => setSuccessMessage(null)}
            countLabel={`${valoresUvt.length} ${valoresUvt.length === 1 ? 'año registrado' : 'años registrados'}`}
            toolbar={
                canCreate && (
                    <Button startIcon={<FileText className="size-4" />} onClick={() => navigate('/gastos/valores-uvt/crear')}>
                        Guardar valor UVT
                    </Button>
                )
            }
        >
            <DataTable
                columns={[
                    { key: 'anio', label: 'Año' },
                    { key: 'valor', label: 'Valor UVT' },
                ]}
                rows={valoresUvt}
                getRowKey={(row) => row.id}
                loading={isLoading}
                emptyMessage="Sin valores de UVT registrados."
            />
        </ModuleListPage>
    );
};
