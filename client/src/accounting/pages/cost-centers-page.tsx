import type { FC } from 'react';
import { useNavigate } from 'react-router';
import { Layers, Search } from 'lucide-react';

import { usePaginatedResource } from '../../shared/hooks/use-paginated-resource';
import { useLocationSuccessMessage } from '../../shared/hooks/use-location-success-message';
import { usePermission } from '../../auth/hooks/use-permission';
import { Button } from '@ui/button/components/button';
import { Input } from '@ui/forms/components/input';
import { ModuleListPage } from '@ui/module/components/module-list-page';
import { DataTable } from '@ui/table/components/data-table';
import { Pagination } from '@ui/table/components/pagination';
import type { CostCentersListLocationState } from '../types/cost-centers-list-location-state';
import type { ApiCostCenter } from '../types/api-cost-center';

export const CostCentersPage: FC = () => {
    const navigate = useNavigate();

    const canCreate = usePermission('contabilidad', 'crear');

    const [successMessage, setSuccessMessage] = useLocationSuccessMessage<CostCentersListLocationState>();

    const { items, isLoading, page, setPage, totalPages, totalItems, search, setSearch } =
        usePaginatedResource<ApiCostCenter>('/contabilidad/centros-costo');

    return (
        <ModuleListPage
            pageTitle="Centros de costo"
            successMessage={successMessage}
            onDismissSuccess={() => setSuccessMessage(null)}
            countLabel={`${totalItems} ${totalItems === 1 ? 'centro registrado' : 'centros registrados'}`}
            toolbar={
                <>
                    <div className="w-full sm:w-72">
                        <Input
                            placeholder="Buscar por código o nombre…"
                            startIcon={<Search className="size-4" />}
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                        />
                    </div>

                    {canCreate && (
                        <Button startIcon={<Layers className="size-4" />} onClick={() => navigate('/contabilidad/centros-costo/crear')}>
                            Crear centro de costo
                        </Button>
                    )}
                </>
            }
            pagination={<Pagination page={page} totalPages={totalPages} onPageChange={setPage} />}
        >
            <DataTable
                columns={[
                    { key: 'codigo', label: 'Código' },
                    { key: 'nombre', label: 'Nombre' },
                    { key: 'activo', label: 'Estado', render: (row) => (row.activo ? 'Activo' : 'Inactivo') },
                ]}
                rows={items}
                getRowKey={(row) => row.id}
                loading={isLoading}
                emptyMessage="Sin centros de costo registrados."
            />
        </ModuleListPage>
    );
};
