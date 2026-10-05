import type { FC } from 'react';
import { useState } from 'react';
import { useNavigate } from 'react-router';
import { CreditCard, Search } from 'lucide-react';

import { PermissionExportButton } from '../../shared/components/permission-export-button';
import { usePaginatedResource } from '../../shared/hooks/use-paginated-resource';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import { useLocationSuccessMessage } from '../../shared/hooks/use-location-success-message';
import { usePermission } from '../../auth/hooks/use-permission';
import { Button } from '@ui/button/components/button';
import { Input } from '@ui/forms/components/input';
import { ConfirmModal } from '@ui/modal/components/confirm-modal';
import { ModuleListPage } from '@ui/module/components/module-list-page';
import { DataTable } from '@ui/table/components/data-table';
import { Pagination } from '@ui/table/components/pagination';
import { buildCreditTableColumns } from '../utils/build-credit-table-columns';
import type { ApiCredit } from '../types/api-credit';
import type { CreditsListLocationState } from '../types/credits-list-location-state';

export const CreditsListPage: FC = () => {
    const navigate = useNavigate();
    const { sendRequest } = useSendRequest();
    const canCreate = usePermission('creditos', 'crear');
    const canCancel = usePermission('creditos', 'cancelar');

    const [creditoACancelar, setCreditoACancelar] = useState<ApiCredit | null>(null);
    const [isCancelling, setIsCancelling] = useState(false);
    const [cancelError, setCancelError] = useState<string | null>(null);
    const [successMessage, setSuccessMessage] = useLocationSuccessMessage<CreditsListLocationState>();
    const [exportMessage, setExportMessage] = useState<string | null>(null);
    const [exportError, setExportError] = useState<string | null>(null);

    const { items, isLoading, page, setPage, totalPages, totalItems, search, setSearch, sortKey, sortDirection, toggleSort, refetch } =
        usePaginatedResource<ApiCredit>('/creditos');

    const confirmarCancelar = async () => {
        if (!creditoACancelar) {
            return;
        }

        setCancelError(null);
        setIsCancelling(true);

        try {
            await sendRequest(`/creditos/${creditoACancelar.id}/cancelar`, { method: 'POST' });
            setCreditoACancelar(null);
            void refetch();
        } catch (caught) {
            setCancelError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setIsCancelling(false);
        }
    };

    const columns = buildCreditTableColumns({
        canCancel,
        onView: (row) => navigate(`/creditos/${row.id}`),
        onCancel: setCreditoACancelar,
    });

    return (
        <ModuleListPage
            pageTitle="Créditos"
            successMessage={successMessage ?? exportMessage}
            onDismissSuccess={() => {
                setSuccessMessage(null);
                setExportMessage(null);
            }}
            errorMessage={cancelError ?? exportError}
            onDismissError={() => {
                setCancelError(null);
                setExportError(null);
            }}
            countLabel={`${totalItems} ${totalItems === 1 ? 'crédito registrado' : 'créditos registrados'}`}
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

                    <PermissionExportButton
                        modulo="creditos"
                        endpoint="/creditos/exportar"
                        filters={{ search }}
                        onRequested={() => {
                            setExportError(null);
                            setExportMessage('Exportación solicitada. Se genera en segundo plano y te avisaremos cuando esté lista para descargar.');
                        }}
                        onError={(message) => {
                            setExportMessage(null);
                            setExportError(message);
                        }}
                    />

                    {canCreate && (
                        <Button collapseBelow="md" startIcon={<CreditCard className="size-4" />} onClick={() => navigate('/creditos/crear')}>
                            Nuevo crédito
                        </Button>
                    )}
                </>
            }
            pagination={<Pagination page={page} totalPages={totalPages} onPageChange={setPage} />}
            modals={
                <ConfirmModal
                    isOpen={creditoACancelar !== null}
                    onClose={() => setCreditoACancelar(null)}
                    onConfirm={() => void confirmarCancelar()}
                    variant="danger"
                    title="Cancelar crédito"
                    description={creditoACancelar ? `¿Seguro que quieres cancelar el crédito #${creditoACancelar.id}?` : ''}
                    confirmLabel="Cancelar crédito"
                    isConfirming={isCancelling}
                />
            }
        >
            <DataTable
                columns={columns}
                rows={items}
                getRowKey={(row) => row.id}
                sortKey={sortKey}
                sortDirection={sortDirection}
                onSortChange={toggleSort}
                loading={isLoading}
                emptyMessage="No hay créditos registrados aún."
            />
        </ModuleListPage>
    );
};
