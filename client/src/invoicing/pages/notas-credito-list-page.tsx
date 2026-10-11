import type { FC } from 'react';
import { useLocation, useNavigate } from 'react-router';
import { FileText, HandCoins, Settings } from 'lucide-react';

import { usePermission } from '../../auth/hooks/use-permission';
import { ModuleListPage } from '@ui/module/components/module-list-page';
import { Button } from '@ui/button/components/button';
import { DataTable } from '@ui/table/components/data-table';
import { Pagination } from '@ui/table/components/pagination';
import { usePaginatedResource } from '../../shared/hooks/use-paginated-resource';
import { formatDateEs } from '../../shared/utils/format-date-es';
import type { ApiInvoice } from '../types/api-invoice';

export const NotasCreditoListPage: FC = () => {
    const navigate = useNavigate();
    const location = useLocation();
    const canCreate = usePermission('facturacion', 'crear');
    const puedeConfigurar = usePermission('facturacion', 'configurar');

    const { items, isLoading, page, setPage, totalPages, totalItems } = usePaginatedResource<ApiInvoice>('/facturacion/documentos', { tipo: 'nota_credito' });

    const mensaje = (location.state as { message?: string } | null)?.message ?? null;

    return (
        <ModuleListPage
            pageTitle="Notas crédito"
            successMessage={mensaje}
            countLabel={`${totalItems} ${totalItems === 1 ? 'documento' : 'documentos'}`}
            toolbar={
                <>
                    {puedeConfigurar && (
                        <Button variant="outline" collapseBelow="md" startIcon={<Settings className="size-4" />} onClick={() => navigate('/facturacion/consecutivos')}>
                            Consecutivos
                        </Button>
                    )}
                    {canCreate && (
                        <Button variant="outline" collapseBelow="md" startIcon={<HandCoins className="size-4" />} onClick={() => navigate('/facturacion/cobros/crear')}>
                            Cobro múltiple
                        </Button>
                    )}
                    {canCreate && (
                        <Button collapseBelow="md" startIcon={<FileText className="size-4" />} onClick={() => navigate('/facturacion/crear')}>
                            Nueva factura
                        </Button>
                    )}
                </>
            }
            pagination={<Pagination page={page} totalPages={totalPages} onPageChange={setPage} />}
        >
            <DataTable
                columns={[
                    { key: 'numero', label: 'Número', render: (row) => row.numero ?? 'Borrador' },
                    { key: 'cliente', label: 'Cliente', render: (row) => row.cliente?.tercero.nombre ?? '—' },
                    { key: 'fecha', label: 'Fecha', render: (row) => formatDateEs(row.fecha) },
                    {
                        key: 'estado',
                        label: 'Estado',
                        render: (row) => (
                            <span
                                className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                    row.estado === 'emitido'
                                        ? 'bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-400'
                                        : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'
                                }`}
                            >
                                {row.estado === 'emitido' ? 'Emitido' : 'Borrador'}
                            </span>
                        ),
                    },
                    {
                        key: 'acciones',
                        label: 'Acciones',
                        render: (row) => (
                            <Button size="sm" variant="outline" onClick={() => navigate(`/facturacion/${row.id}`)}>
                                Ver
                            </Button>
                        ),
                    },
                ]}
                rows={items}
                getRowKey={(row) => row.id}
                loading={isLoading}
                emptyMessage="Todavía no hay notas crédito."
            />
        </ModuleListPage>
    );
};
