import type { FC } from 'react';
import { useState } from 'react';
import { useNavigate, useParams } from 'react-router';

import { useResource } from '../../shared/hooks/use-resource';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import { usePermission } from '../../auth/hooks/use-permission';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { Spinner } from '@ui/spinner/components/spinner';
import { PaymentHistoryTable } from '../../payments/components/payment-history-table';
import { ClientCreditsTable } from '../components/client-credits-table';
import { ClientDocuments } from '../components/client-documents';
import { ClientEmploymentHistoryTable } from '../components/client-employment-history-table';
import { ClientInfoCard } from '../components/client-info-card';
import { ClientSummaryCards } from '../components/client-summary-cards';
import type { ApiClientDocument } from '../types/api-client-document';
import type { ApiClientSummary } from '../types/api-client-summary';

export const ClientDetailPage: FC = () => {
    const { id } = useParams<{ id: string }>();
    const navigate = useNavigate();
    const canEdit = usePermission('clientes', 'editar');

    const { data: resumen, error } = useResource<ApiClientSummary>(`/clientes/${id}/resumen`);
    const { sendRequest } = useSendRequest();

    const [documentosNuevos, setDocumentosNuevos] = useState<ApiClientDocument[]>([]);
    const [uploadError, setUploadError] = useState<string | null>(null);
    const [isUploading, setIsUploading] = useState(false);

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
    } = resumen;
    const documentos = [...documentosNuevos, ...documentosIniciales];

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle={cliente.tercero.nombre} />

            <ClientSummaryCards cliente={cliente} saldoConsolidado={saldoConsolidado} creditosCount={creditos.length} />

            <div className="mb-6">
                <ClientInfoCard cliente={cliente} canEdit={canEdit} onEdit={() => navigate(`/clientes/${cliente.id}/editar`)} />
            </div>

            <ClientEmploymentHistoryTable historial={historialEmpleadores} />

            <ClientCreditsTable creditos={creditos} onView={(creditoId) => navigate(`/creditos/${creditoId}`)} />

            <div className="mb-6">
                <PaymentHistoryTable pagos={ultimosPagos} showCredito onViewCredito={(creditoId) => navigate(`/creditos/${creditoId}`)} />
            </div>

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
