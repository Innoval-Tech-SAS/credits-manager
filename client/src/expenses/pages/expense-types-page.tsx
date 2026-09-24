import type { FC } from 'react';
import { useNavigate } from 'react-router';
import { FileText } from 'lucide-react';

import { useLocationSuccessMessage } from '../../shared/hooks/use-location-success-message';
import { useResource } from '../../shared/hooks/use-resource';
import { usePermission } from '../../auth/hooks/use-permission';
import { Button } from '@ui/button/components/button';
import { ModuleListPage } from '@ui/module/components/module-list-page';
import { DataTable } from '@ui/table/components/data-table';
import type { ExpenseTypesListLocationState } from '../types/expense-types-list-location-state';
import type { ApiExpenseType } from '../types/api-expense-type';

export const ExpenseTypesPage: FC = () => {
    const navigate = useNavigate();

    const canCreate = usePermission('gastos', 'parametrizar');
    const [successMessage, setSuccessMessage] = useLocationSuccessMessage<ExpenseTypesListLocationState>();
    const { data, isLoading } = useResource<{ tipos_gasto: ApiExpenseType[] }>('/gastos/tipos-gasto');
    const tiposGasto = data?.tipos_gasto ?? [];

    return (
        <ModuleListPage
            pageTitle="Tipos de gasto"
            successMessage={successMessage}
            onDismissSuccess={() => setSuccessMessage(null)}
            countLabel={`${tiposGasto.length} ${tiposGasto.length === 1 ? 'tipo registrado' : 'tipos registrados'}`}
            toolbar={
                canCreate && (
                    <Button collapseBelow="md" startIcon={<FileText className="size-4" />} onClick={() => navigate('/gastos/tipos-gasto/crear')}>
                        Crear tipo de gasto
                    </Button>
                )
            }
        >
            <DataTable
                columns={[
                    { key: 'codigo', label: 'Código' },
                    { key: 'nombre', label: 'Nombre' },
                    { key: 'requiere_tercero', label: 'Requiere tercero', render: (tipo) => (tipo.requiere_tercero ? 'Sí' : 'No') },
                    { key: 'aplica_iva', label: 'Aplica IVA', render: (tipo) => (tipo.aplica_iva ? 'Sí' : 'No') },
                    { key: 'activo', label: 'Estado', render: (tipo) => (tipo.activo ? 'Activo' : 'Inactivo') },
                ]}
                rows={tiposGasto}
                getRowKey={(row) => row.id}
                loading={isLoading}
                emptyMessage="Sin tipos de gasto registrados."
            />
        </ModuleListPage>
    );
};
