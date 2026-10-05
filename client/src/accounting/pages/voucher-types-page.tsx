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
import type { VoucherTypesListLocationState } from '../types/voucher-types-list-location-state';
import type { ApiVoucherType } from '../types/api-voucher-type';

export const VoucherTypesPage: FC = () => {
    const navigate = useNavigate();

    const canCreate = usePermission('configuracion_contable', 'crear');

    const [successMessage, setSuccessMessage] = useLocationSuccessMessage<VoucherTypesListLocationState>();

    const { items, isLoading, page, setPage, totalPages, totalItems, search, setSearch } =
        usePaginatedResource<ApiVoucherType>('/contabilidad/tipos-comprobante');

    return (
        <ModuleListPage
            pageTitle="Tipos de comprobante"
            successMessage={successMessage}
            onDismissSuccess={() => setSuccessMessage(null)}
            countLabel={`${totalItems} ${totalItems === 1 ? 'tipo registrado' : 'tipos registrados'}`}
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
                        <Button collapseBelow="md" startIcon={<FileText className="size-4" />} onClick={() => navigate('/contabilidad/tipos-comprobante/crear')}>
                            Crear tipo de comprobante
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
                    { key: 'consecutivo_actual', label: 'Consecutivo actual' },
                ]}
                rows={items}
                getRowKey={(row) => row.id}
                loading={isLoading}
                emptyMessage="Sin tipos de comprobante registrados."
            />
        </ModuleListPage>
    );
};
