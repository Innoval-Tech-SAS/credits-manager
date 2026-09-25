import type { FC } from 'react';
import { useState } from 'react';

import { useSendRequest } from '../../shared/hooks/use-send-request';
import { Button } from '@ui/button/components/button';
import { Label } from '@ui/forms/components/label';
import { Modal } from '@ui/modal/components/modal';
import { Spinner } from '@ui/spinner/components/spinner';
import type { ReassignAdvisorModalProps } from '../props/reassign-advisor-modal-props';
import { AdvisorSelect } from './advisor-select';

export const ReassignAdvisorModal: FC<ReassignAdvisorModalProps> = ({ isOpen, onClose, clientId, currentAdvisorId, onReassigned }) => {
    const { sendRequest } = useSendRequest();

    const [advisorId, setAdvisorId] = useState<number | null>(null);
    const [motivo, setMotivo] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleConfirm = async () => {
        setError(null);
        setIsSubmitting(true);

        try {
            await sendRequest(`/clientes/${clientId}/asesor`, { method: 'PATCH', body: { asesor_id: advisorId, motivo } });

            setAdvisorId(null);
            setMotivo('');
            onReassigned();
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <Modal isOpen={isOpen} onClose={onClose} size="sm">
            <h4 className="mb-2 text-lg font-semibold text-gray-800 dark:text-white/90">Reasignar asesor</h4>
            <p className="mb-5 text-sm text-gray-500 dark:text-gray-400">
                El cliente y sus créditos pasan al nuevo asesor. El cambio queda en el historial con su motivo.
            </p>

            {error && (
                <div className="mb-4 rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                    {error}
                </div>
            )}

            <div className="space-y-4">
                <div>
                    <Label htmlFor="nuevo-asesor">Nuevo asesor</Label>
                    <AdvisorSelect value={advisorId} onChange={setAdvisorId} excludeId={currentAdvisorId} />
                </div>

                <div>
                    <Label htmlFor="motivo-reasignacion">Motivo</Label>
                    <textarea
                        id="motivo-reasignacion"
                        required
                        rows={3}
                        maxLength={255}
                        value={motivo}
                        onChange={(event) => setMotivo(event.target.value)}
                        className="h-auto w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                    />
                </div>
            </div>

            <div className="mt-6 flex justify-end gap-3">
                <Button variant="outline" onClick={onClose} disabled={isSubmitting}>
                    Cancelar
                </Button>
                <Button
                    onClick={() => void handleConfirm()}
                    disabled={isSubmitting || advisorId === null || motivo.trim() === ''}
                    startIcon={isSubmitting ? <Spinner size="sm" color="white" /> : undefined}
                >
                    {isSubmitting ? 'Reasignando…' : 'Confirmar'}
                </Button>
            </div>
        </Modal>
    );
};
