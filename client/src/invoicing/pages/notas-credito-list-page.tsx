import type { FC } from 'react';
import { useState } from 'react';
import { useLocation, useNavigate } from 'react-router';
import { Check, Eye, FileCheck, HandCoins, Settings } from 'lucide-react';

import { useAuth } from '../../auth/hooks/use-auth';
import { usePermission } from '../../auth/hooks/use-permission';
import type { ApiClient } from '../../clients/types/api-client';
import { ModuleListPage } from '@ui/module/components/module-list-page';
import { ActionButton } from '@ui/action-button/components/action-button';
import { Button } from '@ui/button/components/button';
import { CreateButton } from '@ui/button/components/create-button';
import { DataTable } from '@ui/table/components/data-table';
import { Pagination } from '@ui/table/components/pagination';
import { usePaginatedResource } from '../../shared/hooks/use-paginated-resource';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import { formatDateEs } from '../../shared/utils/format-date-es';
import { InvoiceNoteFilters } from '../components/invoice-note-filters';
import type { ApiInvoice } from '../types/api-invoice';

const ESTADO_LABELS: Record<ApiInvoice['estado'], string> = {
    borrador: 'Borrador',
    pendiente_aprobacion: 'Pendiente de aprobación',
    emitido: 'Emitido',
};

const ESTADO_BADGE_CLASSES: Record<ApiInvoice['estado'], string> = {
    borrador: 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
    pendiente_aprobacion: 'bg-warning-50 text-warning-600 dark:bg-warning-500/10 dark:text-warning-400',
    emitido: 'bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-400',
};

export const NotasCreditoListPage: FC = () => {
    const navigate = useNavigate();
    const location = useLocation();
    const { sendRequest, error: errorAprobar } = useSendRequest();
    const { user: usuario } = useAuth();
    // Mismo permiso que habilita el tipo "nota crédito" en el formulario de emisión (ver `invoice-note-form.tsx`).
    const puedeCrear = usePermission('facturacion', 'emitir_nota_credito');
    const puedeAprobar = usePermission('facturacion', 'aprobar_nota_credito');
    const canRegisterPayment = usePermission('facturacion', 'registrar_cobro');
    const puedeConfigurar = usePermission('facturacion', 'configurar');

    const [cliente, setCliente] = useState<ApiClient | null>(null);
    const [estado, setEstado] = useState('');
    const [desde, setDesde] = useState('');
    const [hasta, setHasta] = useState('');
    const [factura, setFactura] = useState('');
    const [motivo, setMotivo] = useState('');

    const { items, isLoading, page, setPage, totalPages, totalItems, refetch } = usePaginatedResource<ApiInvoice>('/facturacion/documentos', {
        tipo: 'nota_credito',
        cliente_id: cliente ? String(cliente.id) : '',
        estado,
        desde,
        hasta,
        factura,
        motivo_codigo: motivo,
    });

    // Cada filtro vuelve a la primera página: una página vacía tras filtrar no tiene sentido.
    const alCambiarFiltro = <T,>(aplicar: (valor: T) => void) => (valor: T) => {
        aplicar(valor);
        setPage(1);
    };

    // La factura origen depende del cliente seleccionado: si cambia el cliente, el filtro de factura queda obsoleto.
    const alCambiarCliente = (nuevoCliente: ApiClient | null) => {
        setCliente(nuevoCliente);
        setFactura('');
        setPage(1);
    };

    const mensaje = (location.state as { message?: string } | null)?.message ?? null;

    const aprobar = async (nota: ApiInvoice) => {
        try {
            await sendRequest(`/facturacion/documentos/${nota.id}/aprobar`, { method: 'POST' });
            void refetch();
        } catch {
            // El mensaje ya queda disponible en `errorAprobar`, mostrado arriba de la tabla.
        }
    };

    return (
        <ModuleListPage
            pageTitle="Notas crédito"
            successMessage={mensaje}
            errorMessage={errorAprobar}
            countLabel={`${totalItems} ${totalItems === 1 ? 'documento' : 'documentos'}`}
            toolbar={
                <>
                    {puedeConfigurar && (
                        <Button variant="outline" collapseBelow="md" startIcon={<Settings className="size-4" />} onClick={() => navigate('/facturacion/consecutivos')}>
                            Consecutivos
                        </Button>
                    )}
                    {canRegisterPayment && (
                        <Button variant="outline" collapseBelow="md" startIcon={<HandCoins className="size-4" />} onClick={() => navigate('/facturacion/cobros/crear')}>
                            Cobro múltiple
                        </Button>
                    )}
                    {puedeCrear && (
                        <CreateButton
                            label="nota crédito"
                            icon={<FileCheck className="size-4" />}
                            onClick={() => navigate('/facturacion/notas/crear?tipo=nota_credito')}
                        />
                    )}
                </>
            }
            pagination={<Pagination page={page} totalPages={totalPages} onPageChange={setPage} />}
        >
            <InvoiceNoteFilters
                tipo="nota_credito"
                cliente={cliente}
                onClienteChange={alCambiarCliente}
                estado={estado}
                onEstadoChange={alCambiarFiltro(setEstado)}
                desde={desde}
                onDesdeChange={alCambiarFiltro(setDesde)}
                hasta={hasta}
                onHastaChange={alCambiarFiltro(setHasta)}
                factura={factura}
                onFacturaChange={alCambiarFiltro(setFactura)}
                motivo={motivo}
                onMotivoChange={alCambiarFiltro(setMotivo)}
            />

            <DataTable
                columns={[
                    { key: 'numero', label: 'Número', render: (row) => row.numero ?? 'Borrador' },
                    { key: 'cliente', label: 'Cliente', render: (row) => row.cliente?.tercero.nombre ?? '—' },
                    { key: 'fecha', label: 'Fecha', render: (row) => formatDateEs(row.fecha) },
                    {
                        key: 'estado',
                        label: 'Estado',
                        render: (row) => (
                            <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${ESTADO_BADGE_CLASSES[row.estado]}`}>
                                {ESTADO_LABELS[row.estado]}
                            </span>
                        ),
                    },
                    {
                        key: 'acciones',
                        label: 'Acciones',
                        render: (row) => (
                            <div className="flex items-center gap-1">
                                {puedeAprobar && row.estado === 'pendiente_aprobacion' && row.solicitado_por !== usuario?.id && (
                                    <ActionButton icon={<Check className="size-5" />} label="Aprobar" onClick={() => void aprobar(row)} />
                                )}
                                <ActionButton icon={<Eye className="size-5" />} label="Ver" onClick={() => navigate(`/facturacion/${row.id}`)} />
                            </div>
                        ),
                    },
                ]}
                rows={items}
                getRowKey={(row) => row.id}
                loading={isLoading}
                emptyMessage="No hay notas crédito con esos filtros."
                maxHeightClassName="max-h-[65vh]"
            />
        </ModuleListPage>
    );
};
