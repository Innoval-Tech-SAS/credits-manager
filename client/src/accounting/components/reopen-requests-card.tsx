import type { FC } from 'react';
import { useState } from 'react';

import { useSendRequest } from '../../shared/hooks/use-send-request';
import { formatDateTimeEs } from '../../shared/utils/format-datetime-es';
import { Alert } from '@ui/alert/components/alert';
import { Button } from '@ui/button/components/button';
import { Label } from '@ui/forms/components/label';
import { Modal } from '@ui/modal/components/modal';
import { MONTHS } from '../constants/months';
import { REOPEN_REQUEST_STATUS_BADGE_CLASSES, REOPEN_REQUEST_STATUS_LABEL } from '../constants/reopen-request-status';
import type { ReopenRequestsCardProps } from '../props/reopen-requests-card-props';
import type { ApiReopenRequest } from '../types/api-reopen-request';

export const ReopenRequestsCard: FC<ReopenRequestsCardProps> = ({ solicitudes, canResolve, onResolved }) => {
    const { sendRequest } = useSendRequest();

    const [authorizingId, setAuthorizingId] = useState<number | null>(null);
    const [error, setError] = useState<string | null>(null);

    const [requestToReject, setRequestToReject] = useState<ApiReopenRequest | null>(null);
    const [rejectionReason, setRejectionReason] = useState('');
    const [isRejecting, setIsRejecting] = useState(false);
    const [rejectError, setRejectError] = useState<string | null>(null);

    const closeRejectModal = () => {
        setRequestToReject(null);
        setRejectionReason('');
        setRejectError(null);
    };

    const handleAuthorize = async (solicitud: ApiReopenRequest) => {
        setError(null);
        setAuthorizingId(solicitud.id);

        try {
            await sendRequest(`/contabilidad/solicitudes-reapertura/${solicitud.id}/autorizar`, { method: 'POST' });
            onResolved();
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setAuthorizingId(null);
        }
    };

    const handleReject = async () => {
        if (!requestToReject) {
            return;
        }

        setRejectError(null);
        setIsRejecting(true);

        try {
            await sendRequest(`/contabilidad/solicitudes-reapertura/${requestToReject.id}/rechazar`, {
                method: 'POST',
                body: { motivo_resolucion: rejectionReason },
            });
            closeRejectModal();
            onResolved();
        } catch (caught) {
            setRejectError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setIsRejecting(false);
        }
    };

    return (
        <div className="mb-6 rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 dark:border-gray-800 dark:bg-white/3">
            <h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">Solicitudes de reapertura</h2>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {canResolve ? 'Autoriza o rechaza las solicitudes del área contable.' : 'Estado de las solicitudes que has hecho.'}
            </p>

            {error && (
                <div className="mt-4">
                    <Alert variant="danger" title="Error" message={error} onDismiss={() => setError(null)} />
                </div>
            )}

            <ul className="mt-4 divide-y divide-gray-100 dark:divide-gray-800">
                {solicitudes.map((solicitud) => (
                    <li key={solicitud.id} className="flex flex-col gap-3 py-4 first:pt-0 last:pb-0">
                        <div className="flex items-start justify-between gap-3">
                            <div className="min-w-0">
                                <p className="text-sm font-medium text-gray-800 dark:text-white/90">
                                    {solicitud.periodo ? `${MONTHS[solicitud.periodo.mes - 1] ?? solicitud.periodo.mes} ${solicitud.periodo.anio}` : 'Período'}
                                </p>
                                <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                    {solicitud.solicitante?.nombre ?? '—'} · {formatDateTimeEs(solicitud.creado_en)}
                                </p>
                            </div>
                            <span
                                className={`shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium ${REOPEN_REQUEST_STATUS_BADGE_CLASSES[solicitud.estado]}`}
                            >
                                {REOPEN_REQUEST_STATUS_LABEL[solicitud.estado]}
                            </span>
                        </div>

                        <p className="text-sm text-gray-700 dark:text-gray-300">{solicitud.motivo}</p>

                        {solicitud.motivo_resolucion && (
                            <p className="rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-600 dark:bg-white/5 dark:text-gray-400">
                                Motivo del rechazo: {solicitud.motivo_resolucion}
                            </p>
                        )}

                        {canResolve && solicitud.estado === 'pendiente' && (
                            <div className="flex items-center gap-3">
                                <Button size="sm" onClick={() => void handleAuthorize(solicitud)} disabled={authorizingId === solicitud.id}>
                                    {authorizingId === solicitud.id ? 'Autorizando…' : 'Autorizar'}
                                </Button>
                                <Button size="sm" variant="outline" onClick={() => setRequestToReject(solicitud)}>
                                    Rechazar
                                </Button>
                            </div>
                        )}
                    </li>
                ))}
            </ul>

            <Modal isOpen={requestToReject !== null} onClose={closeRejectModal} size="sm">
                <h4 className="mb-4 text-lg font-semibold text-gray-800 dark:text-white/90">Rechazar solicitud</h4>

                {rejectError && (
                    <div className="mb-4 rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                        {rejectError}
                    </div>
                )}

                <Label htmlFor="motivo-rechazo">Motivo del rechazo</Label>
                <textarea
                    id="motivo-rechazo"
                    required
                    rows={3}
                    value={rejectionReason}
                    onChange={(event) => setRejectionReason(event.target.value)}
                    className="h-auto w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                />

                <div className="mt-6 flex justify-end gap-3">
                    <Button variant="outline" onClick={closeRejectModal} disabled={isRejecting}>
                        Cancelar
                    </Button>
                    <Button onClick={() => void handleReject()} disabled={isRejecting || rejectionReason.trim() === ''}>
                        {isRejecting ? 'Rechazando…' : 'Rechazar solicitud'}
                    </Button>
                </div>
            </Modal>
        </div>
    );
};
