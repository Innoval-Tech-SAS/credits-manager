import type { FC } from 'react';
import { useState } from 'react';
import { useNavigate } from 'react-router';
import { HandCoins, Search } from 'lucide-react';

import { PermissionExportButton } from '../../shared/components/permission-export-button';
import { MotivoModal } from '../../shared/components/motivo-modal';
import { usePaginatedResource } from '../../shared/hooks/use-paginated-resource';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import { usePermission } from '../../auth/hooks/use-permission';
import { Button } from '@ui/button/components/button';
import { DatePicker } from '@ui/forms/components/date-picker';
import { Input } from '@ui/forms/components/input';
import { ModuleListPage } from '@ui/module/components/module-list-page';
import { DataTable } from '@ui/table/components/data-table';
import { Pagination } from '@ui/table/components/pagination';
import { buildPaymentTableColumns } from '../utils/build-payment-table-columns';
import type { ApiPaymentListItem } from '../types/api-payment-list-item';

export const PaymentsListPage: FC = () => {
    const navigate = useNavigate();
    const canRegisterPayment = usePermission('pagos', 'crear');
    const canReversePayment = usePermission('pagos', 'reversar');
    const { sendRequest } = useSendRequest();

    const [mes, setMes] = useState('');
    const [successMessage, setSuccessMessage] = useState<string | null>(null);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const [pagoAReversar, setPagoAReversar] = useState<ApiPaymentListItem | null>(null);
    const [isReversing, setIsReversing] = useState(false);

    const { items, isLoading, page, setPage, totalPages, totalItems, search, setSearch, refetch } = usePaginatedResource<ApiPaymentListItem>(
        '/pagos',
        { mes },
    );

    const columns = buildPaymentTableColumns({
        onViewCredito: (creditoId) => navigate(`/creditos/${creditoId}`),
        onReversar: canReversePayment ? setPagoAReversar : null,
    });

    const reversar = async (motivo: string) => {
        if (!pagoAReversar) {
            return;
        }

        setIsReversing(true);
        setErrorMessage(null);

        try {
            await sendRequest(`/pagos/${pagoAReversar.id}/reversar`, { method: 'POST', body: { motivo } });
            setPagoAReversar(null);
            setSuccessMessage(`El pago #${pagoAReversar.id} fue reversado. El recibo quedó anulado y el saldo de las cuotas se restauró.`);
            refetch();
        } catch (caught) {
            setPagoAReversar(null);
            setErrorMessage(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setIsReversing(false);
        }
    };

    return (
        <>
            <ModuleListPage
                pageTitle="Pagos"
                successMessage={successMessage}
                onDismissSuccess={() => setSuccessMessage(null)}
                errorMessage={errorMessage}
                onDismissError={() => setErrorMessage(null)}
                countLabel={`${totalItems} ${totalItems === 1 ? 'pago registrado' : 'pagos registrados'}`}
                toolbar={
                    <>
                        <div className="w-full sm:w-72">
                            <Input
                                id="buscar-pago"
                                name="buscar-pago"
                                placeholder="Buscar por cliente…"
                                startIcon={<Search className="size-4" />}
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                            />
                        </div>

                        <div className="flex w-full items-center gap-2 sm:w-auto">
                            <div className="w-full sm:w-44">
                                <DatePicker
                                    id="mes-pago"
                                    mode="month"
                                    value={mes}
                                    onChange={(value) => {
                                        setMes(value);
                                        setPage(1);
                                    }}
                                />
                            </div>
                            {mes && (
                                <Button variant="outline" onClick={() => { setMes(''); setPage(1); }}>
                                    Todos
                                </Button>
                            )}
                        </div>

                        <PermissionExportButton
                            permiso="pagos.exportar_listado"
                            endpoint="/pagos/exportar"
                            filters={{ search, mes }}
                            onRequested={() => {
                                setErrorMessage(null);
                                setSuccessMessage('Exportación solicitada. Se genera en segundo plano y te avisaremos cuando esté lista para descargar.');
                            }}
                            onError={(message) => {
                                setSuccessMessage(null);
                                setErrorMessage(message);
                            }}
                        />

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
                    emptyMessage={mes ? 'No hay pagos registrados en este mes.' : 'No hay pagos registrados aún.'}
                    maxHeightClassName="max-h-[65vh]"
                />
            </ModuleListPage>

            <MotivoModal
                isOpen={pagoAReversar !== null}
                title={`Reversar el pago #${pagoAReversar?.id ?? ''}`}
                description="Las cuotas recuperarán lo aplicado y el recibo quedará anulado. Indique el motivo."
                confirmLabel="Reversar pago"
                isSubmitting={isReversing}
                onClose={() => setPagoAReversar(null)}
                onConfirm={reversar}
            />
        </>
    );
};
