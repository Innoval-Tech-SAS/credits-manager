import type { FC } from 'react';
import { useState } from 'react';
import { useNavigate, useParams } from 'react-router';

import { useResource } from '../../shared/hooks/use-resource';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import { usePermission } from '../../auth/hooks/use-permission';
import { Alert } from '@ui/alert/components/alert';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { Spinner } from '@ui/spinner/components/spinner';
import { PaymentHistoryTable } from '../../payments/components/payment-history-table';
import { ClientAdvisorHistoryCard } from '../components/client-advisor-history-card';
import { ClientCreditsTable } from '../components/client-credits-table';
import { ClientDocuments } from '../components/client-documents';
import { ClientEmploymentHistoryTable } from '../components/client-employment-history-table';
import { ClientInfoCard } from '../components/client-info-card';
import { ClientMonthlyBehaviorCard } from '../components/client-monthly-behavior-card';
import { ClientSummaryCards } from '../components/client-summary-cards';
import { WhatsAppButton } from '../components/whatsapp-button';
import { EmailDispatchesCard } from '../../email/components/email-dispatches-card';
import { EmailReminderButton } from '../../email/components/email-reminder-button';
import type { ApiReminderHistory } from '../../email/types/api-email-dispatch';
import type { ApiClientDocument } from '../types/api-client-document';
import type { ApiClientSummary } from '../types/api-client-summary';

export const ClientDetailPage: FC = () => {
    const { id } = useParams<{ id: string }>();
    const navigate = useNavigate();
    const canEdit = usePermission('clientes', 'editar');
    const canReassignAdvisor = usePermission('clientes', 'reasignar_asesor');

    const { data: resumen, error, refetch } = useResource<ApiClientSummary>(`/clientes/${id}/resumen`);
    const canSendReminder = usePermission('cartera', 'enviar_recordatorio');
    const { data: recordatorios, refetch: refetchRecordatorios } = useResource<ApiReminderHistory>(`/clientes/${id}/recordatorios-correo`);
    const { sendRequest } = useSendRequest();

    const [documentosNuevos, setDocumentosNuevos] = useState<ApiClientDocument[]>([]);
    const [uploadError, setUploadError] = useState<string | null>(null);
    const [isUploading, setIsUploading] = useState(false);
    const [retryingId, setRetryingId] = useState<number | null>(null);
    const [retryError, setRetryError] = useState<string | null>(null);

    const handleRetry = async (envioId: number) => {
        setRetryError(null);
        setRetryingId(envioId);

        try {
            await sendRequest(`/clientes/${id}/recordatorios-correo/${envioId}/reintentar`, { method: 'POST' });
            refetchRecordatorios();
        } catch (caught) {
            setRetryError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setRetryingId(null);
        }
    };

    const handleUpload = async (archivo: File) => {
        setUploadError(null);
        setIsUploading(true);

        const formData = new FormData();
        formData.append('archivo', archivo);

        try {
            const respuesta = await sendRequest<{ documento: ApiClientDocument }>(`/clientes/${id}/documentos`, {
                method: 'POST',
                body: formData,
            });
            setDocumentosNuevos((current) => [respuesta.documento, ...current]);
        } catch (caught) {
            setUploadError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setIsUploading(false);
        }
    };

    if (error) {
        return (
            <div className="rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                {error}
            </div>
        );
    }

    if (!resumen) {
        return (
            <div className="flex justify-center py-12">
                <Spinner size="lg" />
            </div>
        );
    }

    const {
        cliente,
        creditos,
        saldo_consolidado: saldoConsolidado,
        ultimos_pagos: ultimosPagos,
        documentos: documentosIniciales,
        historial_empleadores: historialEmpleadores,
        comportamiento_mensual: comportamientoMensual,
    } = resumen;
    const documentos = [...documentosNuevos, ...documentosIniciales];

    return (
        <div className="flex min-h-full flex-col pb-6">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <PageBreadcrumb pageTitle={cliente.tercero.nombre} />
                <div className="flex flex-wrap items-center gap-2">
                    <WhatsAppButton variant="boton" nombre={cliente.tercero.nombre} celular={cliente.tercero.celular} />
                    {canSendReminder && recordatorios && (
                        <EmailReminderButton
                            clienteId={cliente.id}
                            correo={cliente.tercero.correo}
                            cuotasCobrables={recordatorios.cuotas_cobrables}
                            onSent={refetchRecordatorios}
                        />
                    )}
                </div>
            </div>

            <ClientSummaryCards cliente={cliente} saldoConsolidado={saldoConsolidado} creditosCount={creditos.length} />

            <ClientMonthlyBehaviorCard comportamiento={comportamientoMensual} />

            <div className="mb-6">
                <ClientInfoCard cliente={cliente} canEdit={canEdit} onEdit={() => navigate(`/clientes/${cliente.id}/editar`)} />
            </div>

            {canReassignAdvisor && <ClientAdvisorHistoryCard clientId={cliente.id} currentAdvisorId={cliente.asesor_id} onReassigned={refetch} />}

            <ClientEmploymentHistoryTable historial={historialEmpleadores} />

            <ClientCreditsTable creditos={creditos} onView={(creditoId) => navigate(`/creditos/${creditoId}`)} />

            <div className="mb-6">
                <PaymentHistoryTable pagos={ultimosPagos} showCredito onViewCredito={(creditoId) => navigate(`/creditos/${creditoId}`)} />
            </div>

            {retryError && (
                <div className="mb-6">
                    <Alert variant="danger" title="No se pudo reintentar el envío" message={retryError} onDismiss={() => setRetryError(null)} />
                </div>
            )}

            {recordatorios && (
                <EmailDispatchesCard
                    envios={recordatorios.envios}
                    onRetry={canSendReminder ? (envioId) => void handleRetry(envioId) : undefined}
                    retryingId={retryingId}
                />
            )}

            <ClientDocuments
                documentos={documentos}
                canUpload={canEdit}
                isUploading={isUploading}
                uploadError={uploadError}
                onUpload={(archivo) => void handleUpload(archivo)}
            />
        </div>
    );
};
