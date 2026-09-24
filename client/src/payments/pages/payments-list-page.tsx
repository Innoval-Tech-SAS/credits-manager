import type { FC } from 'react';
import { useNavigate } from 'react-router';
import { HandCoins, Search } from 'lucide-react';

import { usePaginatedResource } from '../../shared/hooks/use-paginated-resource';
import { usePermission } from '../../auth/hooks/use-permission';
import { Button } from '@ui/button/components/button';
import { Input } from '@ui/forms/components/input';
import { ModuleListPage } from '@ui/module/components/module-list-page';
import { DataTable } from '@ui/table/components/data-table';
import { Pagination } from '@ui/table/components/pagination';
import { buildPaymentTableColumns } from '../utils/build-payment-table-columns';
import type { ApiPaymentListItem } from '../types/api-payment-list-item';

export const PaymentsListPage: FC = () => {
    const navigate = useNavigate();
    const canRegisterPayment = usePermission('pagos', 'crear');

    const { items, isLoading, page, setPage, totalPages, totalItems, search, setSearch } = usePaginatedResource<ApiPaymentListItem>('/pagos');

    const columns = buildPaymentTableColumns((creditoId) => navigate(`/creditos/${creditoId}`));

    return (
        <ModuleListPage
            pageTitle="Pagos"
            countLabel={`${totalItems} ${totalItems === 1 ? 'pago registrado' : 'pagos registrados'}`}
            toolbar={
                <>
                    <div className="w-full sm:w-72">
                        <Input
                            placeholder="Buscar por cliente…"
                            startIcon={<Search className="size-4" />}
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                        />
                    </div>

                    {canRegisterPayment && (
                        <Button collapseBelow="md" startIcon={<HandCoins className="size-4" />} onClick={() => navigate('/pagos/buscar')}>
                            Registrar pago
                        </Button>
                    )}
                </>
            }
            pagination={<Pagination page={page} totalPages={totalPages} onPageChange={setPage} />}
        >
            <DataTable
                columns={columns}
                rows={items}
                getRowKey={(row) => row.id}
                loading={isLoading}
                emptyMessage="No hay pagos registrados aún."
            />
        </ModuleListPage>
    );
};
