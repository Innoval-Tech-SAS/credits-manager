import type { FC } from 'react';
import { useEffect, useRef, useState } from 'react';
import { useNavigate, useParams } from 'react-router';
import { Ban, Download } from 'lucide-react';

import { useDownloadFile } from '../../shared/hooks/use-download-file';
import { useResource } from '../../shared/hooks/use-resource';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import { Alert } from '@ui/alert/components/alert';
import { Button } from '@ui/button/components/button';
import { ConfirmModal } from '@ui/modal/components/confirm-modal';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { Spinner } from '@ui/spinner/components/spinner';
import { ImportDecisionModals } from '../components/import-decision-modals';
import { ImportErrorsTable } from '../components/import-errors-table';
import { ImportStatusBadge } from '../components/import-status-badge';
import { ImportSteps } from '../components/import-steps';
import { IMPORT_TRANSITIONAL_STATUSES, type ImportStatus } from '../constants/import-status';
import { IMPORT_TYPE_LABELS } from '../constants/import-types';
import type { ApiImportDetails } from '../types/api-import-details';
import { formatImportDate } from '../utils/format-import-date';
import { importStepIndex } from '../utils/import-step-index';

const STEPS = ['Preparar', 'Subir', 'Validar', 'Decidir', 'Escribir', 'Resultado'];

const TRANSITIONAL_MESSAGES: Partial<Record<ImportStatus, string>> = {
    pendiente: 'Tu archivo está en cola para validarse.',
    validando: 'Estamos validando cada fila. No se escribe nada todavía.',
    importando: 'Estamos escribiendo las filas aceptadas. Puedes salir de esta página: te avisaremos al terminar.',
};

export const ImportDetailPage: FC = () => {
    const { id } = useParams();
    const navigate = useNavigate();
    const importId = Number(id);

    const { data, refetch } = useResource<ApiImportDetails>(Number.isInteger(importId) ? `/importaciones/${importId}` : null);
    const { sendRequest } = useSendRequest();
    const { download, isDownloading } = useDownloadFile();

    const [actionError, setActionError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [isCancelOpen, setIsCancelOpen] = useState(false);

    const estado = data?.importacion.estado;
    const refetchRef = useRef(refetch);

    useEffect(() => {
        refetchRef.current = refetch;
    });

    useEffect(() => {
        if (estado === undefined || !IMPORT_TRANSITIONAL_STATUSES.includes(estado)) {
            return;
        }

        const intervalo = window.setInterval(() => refetchRef.current(), 3000);

        return () => window.clearInterval(intervalo);
    }, [estado]);

    if (!data) {
        return (
            <div className="flex justify-center py-12">
                <Spinner />
            </div>
        );
    }

    const { importacion, decisiones } = data;
    const esTransitorio = IMPORT_TRANSITIONAL_STATUSES.includes(importacion.estado);
    const esTerminal = ['completada', 'fallida', 'cancelada'].includes(importacion.estado);
    const hayErrores = importacion.filas_rechazadas > 0 || importacion.empleadores_pendientes > 0;
    const decisionesAbiertas = decisiones.requiere_decision_empleadores || decisiones.requiere_decision_asiento;

    const ejecutar = async (path: string, body?: Record<string, boolean>) => {
        setActionError(null);
        setIsSubmitting(true);

        try {
            await sendRequest(path, { method: 'POST', body });
            refetch();
        } catch (caught) {
            setActionError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setIsSubmitting(false);
        }
    };

    const handleCancelar = async () => {
        setIsCancelOpen(false);
        await ejecutar(`/importaciones/${importacion.id}/cancelar`);
    };

    return (
        <div className="flex flex-col gap-6">
            <PageBreadcrumb pageTitle="Importación" />

            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="min-w-0">
                    <h2 className="truncate text-lg font-semibold text-gray-800 dark:text-white/90">{importacion.archivo_original}</h2>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        {IMPORT_TYPE_LABELS[importacion.tipo]} · {formatImportDate(importacion.creado_en)}
                        {importacion.usuario ? ` · ${importacion.usuario.nombre}` : ''}
                    </p>
                </div>
                <ImportStatusBadge status={importacion.estado} />
            </div>

            <ImportSteps steps={STEPS} currentStep={importStepIndex(importacion.estado)} />

            {actionError && <Alert variant="danger" title="No se pudo completar la acción" message={actionError} onDismiss={() => setActionError(null)} />}

            {esTransitorio && (
                <section className="flex items-center gap-4 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3">
                    <Spinner />
                    <p className="text-sm text-gray-600 dark:text-gray-300">{TRANSITIONAL_MESSAGES[importacion.estado]}</p>
                </section>
            )}

            {(importacion.estado === 'pendiente_decision' || esTerminal) && (
                <section className="grid grid-cols-2 gap-4 md:grid-cols-4">
                    {[
                        { label: 'Aceptadas', value: importacion.filas_aceptadas },
                        { label: 'Rechazadas', value: importacion.filas_rechazadas },
                        { label: 'Esperan empleador', value: importacion.filas_pendientes },
                        { label: 'Empleadores faltantes', value: importacion.empleadores_pendientes },
                    ].map((tile) => (
                        <div key={tile.label} className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3">
                            <p className="text-sm text-gray-500 dark:text-gray-400">{tile.label}</p>
                            <p className="mt-1 text-title-sm font-semibold text-gray-800 dark:text-white/90">{tile.value}</p>
                        </div>
                    ))}
                </section>
            )}

            {importacion.estado === 'fallida' && importacion.error && (
                <Alert variant="danger" title="La importación falló" message={importacion.error} />
            )}

            {importacion.estado === 'pendiente_decision' && (
                <section className="flex flex-col gap-3 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3 sm:flex-row sm:items-center sm:justify-between">
                    <p className="text-sm text-gray-600 dark:text-gray-300">
                        {decisionesAbiertas
                            ? 'Responde las preguntas pendientes para poder confirmar.'
                            : 'Revisa el resumen. Al confirmar se escriben las filas aceptadas; las rechazadas quedan en el reporte.'}
                    </p>
                    <div className="flex flex-col gap-3 sm:flex-row">
                        <Button variant="outline" startIcon={<Ban className="size-4" />} disabled={isSubmitting} onClick={() => setIsCancelOpen(true)}>
                            Cancelar importación
                        </Button>
                        <Button disabled={isSubmitting || decisionesAbiertas} onClick={() => void ejecutar(`/importaciones/${importacion.id}/confirmar`)}>
                            Confirmar e importar
                        </Button>
                    </div>
                </section>
            )}

            {(esTerminal || importacion.estado === 'pendiente_decision') && (
                <section className="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <h3 className="text-base font-semibold text-gray-800 dark:text-white/90">Reporte de errores</h3>
                        {hayErrores && (
                            <Button
                                variant="outline"
                                startIcon={<Download className="size-4" />}
                                disabled={isDownloading}
                                onClick={() => void download(`/importaciones/${importacion.id}/reporte`, `reporte-importacion-${importacion.id}.csv`)}
                            >
                                Descargar reporte (CSV)
                            </Button>
                        )}
                    </div>
                    <ImportErrorsTable importId={importacion.id} />
                </section>
            )}

            {esTerminal && importacion.estado === 'completada' && importacion.comprobante_id !== null && (
                <p className="text-sm text-gray-500 dark:text-gray-400">Se generó el asiento de apertura #{importacion.comprobante_id}.</p>
            )}

            <div className="flex justify-start">
                <Button variant="outline" onClick={() => navigate('/importaciones')}>
                    Volver al historial
                </Button>
            </div>

            <ImportDecisionModals
                importacion={importacion}
                decisiones={decisiones}
                isSubmitting={isSubmitting}
                onDecide={(decision, value) => void ejecutar(`/importaciones/${importacion.id}/decisiones`, { [decision]: value })}
            />

            <ConfirmModal
                isOpen={isCancelOpen}
                onClose={() => setIsCancelOpen(false)}
                onConfirm={() => void handleCancelar()}
                variant="danger"
                title="¿Cancelar la importación?"
                description="No se escribirá ningún dato de este archivo. El registro queda en el historial."
                confirmLabel="Cancelar importación"
                cancelLabel="Volver"
                isConfirming={isSubmitting}
            />
        </div>
    );
};
