import type { FC } from 'react';
import { useNavigate } from 'react-router';
import { Building2, Search } from 'lucide-react';

import { usePaginatedResource } from '../../shared/hooks/use-paginated-resource';
import { useLocationSuccessMessage } from '../../shared/hooks/use-location-success-message';
import { Button } from '@ui/button/components/button';
import { Input } from '@ui/forms/components/input';
import { ModuleListPage } from '@ui/module/components/module-list-page';
import { DataTable } from '@ui/table/components/data-table';
import { Pagination } from '@ui/table/components/pagination';
import { buildCompanyTableColumns } from '../utils/build-company-table-columns';
import type { ApiCompany } from '../types/api-company';
import type { CompaniesListLocationState } from '../types/companies-list-location-state';

export const CompaniesListPage: FC = () => {
    const navigate = useNavigate();

    const [successMessage, setSuccessMessage] = useLocationSuccessMessage<CompaniesListLocationState>();

    const { items, isLoading, page, setPage, totalPages, totalItems, search, setSearch } =
        usePaginatedResource<ApiCompany>('/empresas');

    const columns = buildCompanyTableColumns();

    return (
        <ModuleListPage
            pageTitle="Empresas"
            successMessage={successMessage}
            onDismissSuccess={() => setSuccessMessage(null)}
            countLabel={`${totalItems} ${totalItems === 1 ? 'empresa registrada' : 'empresas registradas'}`}
            toolbar={
                <>
                    <div className="w-full sm:w-72">
                        <Input
                            placeholder="Buscar por nombre…"
                            startIcon={<Search className="size-4" />}
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                        />
                    </div>

                    <Button startIcon={<Building2 className="size-4" />} onClick={() => navigate('/empresas/crear')}>
                        Crear empresa
                    </Button>
                </>
            }
            pagination={<Pagination page={page} totalPages={totalPages} onPageChange={setPage} />}
        >
            <DataTable
                columns={columns}
                rows={items}
                getRowKey={(row) => row.id}
                loading={isLoading}
                emptyMessage="No hay empresas que coincidan con la búsqueda."
            />
        </ModuleListPage>
    );
};
