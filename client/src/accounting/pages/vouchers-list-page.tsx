import type { FC } from 'react';
import { useNavigate } from 'react-router';
import { FilePlus2, FileSpreadsheet, Search } from 'lucide-react';

import { usePaginatedResource } from '../../shared/hooks/use-paginated-resource';
import { useLocationSuccessMessage } from '../../shared/hooks/use-location-success-message';
import { usePermission } from '../../auth/hooks/use-permission';
import { Button } from '@ui/button/components/button';
import { Input } from '@ui/forms/components/input';
import { ModuleListPage } from '@ui/module/components/module-list-page';
import { DataTable } from '@ui/table/components/data-table';
import { Pagination } from '@ui/table/components/pagination';
import { buildVoucherTableColumns } from '../utils/build-voucher-table-columns';
import type { ApiVoucher } from '../types/api-voucher';
import type { VouchersListLocationState } from '../types/vouchers-list-location-state';

export const VouchersListPage: FC = () => {
    const navigate = useNavigate();

    const canCreate = usePermission('contabilidad', 'crear_manual');

    const [successMessage, setSuccessMessage] = useLocationSuccessMessage<VouchersListLocationState>();

    const { items, isLoading, page, setPage, totalPages, totalItems, search, setSearch } =
        usePaginatedResource<ApiVoucher>('/contabilidad/comprobantes');

    const columns = buildVoucherTableColumns();

    return (
        <ModuleListPage
            pageTitle="Comprobantes contables"
            successMessage={successMessage}
            onDismissSuccess={() => setSuccessMessage(null)}
            countLabel={`${totalItems} ${totalItems === 1 ? 'comprobante registrado' : 'comprobantes registrados'}`}
            toolbar={
                <>
                    <div className="w-full sm:w-72">
                        <Input
                            id="buscar-comprobante"
                            name="buscar-comprobante"
                            placeholder="Buscar por concepto o tipo…"
                            startIcon={<Search className="size-4" />}
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                        />
                    </div>

                    {canCreate && (
                        <>
                            <Button
                                variant="outline"
                                collapseBelow="md"
                                startIcon={<FileSpreadsheet className="size-4" />}
                                onClick={() => navigate('/contabilidad/comprobantes/causar-concepto')}
                            >
                                Causar con concepto
                            </Button>
                            <Button collapseBelow="md" startIcon={<FilePlus2 className="size-4" />} onClick={() => navigate('/contabilidad/comprobantes/crear')}>
                                Nuevo comprobante manual
                            </Button>
                        </>
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
                emptyMessage="Sin comprobantes."
                maxHeightClassName="max-h-[65vh]"
            />
        </ModuleListPage>
    );
};
