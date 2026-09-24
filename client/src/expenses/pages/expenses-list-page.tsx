import type { FC } from 'react';
import { useNavigate } from 'react-router';
import { FileText, Search } from 'lucide-react';

import { usePaginatedResource } from '../../shared/hooks/use-paginated-resource';
import { useLocationSuccessMessage } from '../../shared/hooks/use-location-success-message';
import { usePermission } from '../../auth/hooks/use-permission';
import { Button } from '@ui/button/components/button';
import { Input } from '@ui/forms/components/input';
import { ModuleListPage } from '@ui/module/components/module-list-page';
import { DataTable } from '@ui/table/components/data-table';
import { Pagination } from '@ui/table/components/pagination';
import { buildExpenseTableColumns } from '../utils/build-expense-table-columns';
import type { ExpensesListLocationState } from '../types/expenses-list-location-state';
import type { ApiExpense } from '../types/api-expense';

export const ExpensesListPage: FC = () => {
    const navigate = useNavigate();

    const canCreate = usePermission('gastos', 'crear');
    const [successMessage, setSuccessMessage] = useLocationSuccessMessage<ExpensesListLocationState>();

    const { items, isLoading, page, setPage, totalPages, totalItems, search, setSearch } = usePaginatedResource<ApiExpense>('/gastos');

    return (
        <ModuleListPage
            pageTitle="Gastos"
            successMessage={successMessage}
            onDismissSuccess={() => setSuccessMessage(null)}
            countLabel={`${totalItems} ${totalItems === 1 ? 'gasto registrado' : 'gastos registrados'}`}
            toolbar={
                <>
                    <div className="w-full sm:w-72">
                        <Input
                            placeholder="Buscar por documento o concepto…"
                            startIcon={<Search className="size-4" />}
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                        />
                    </div>

                    {canCreate && (
                        <Button collapseBelow="md" startIcon={<FileText className="size-4" />} onClick={() => navigate('/gastos/crear')}>
                            Causar gasto
                        </Button>
                    )}
                </>
            }
            pagination={<Pagination page={page} totalPages={totalPages} onPageChange={setPage} />}
        >
            <DataTable
                columns={buildExpenseTableColumns((gasto) => navigate(`/gastos/${gasto.id}`))}
                rows={items}
                getRowKey={(row) => row.id}
                loading={isLoading}
                emptyMessage="Sin gastos registrados."
            />
        </ModuleListPage>
    );
};
