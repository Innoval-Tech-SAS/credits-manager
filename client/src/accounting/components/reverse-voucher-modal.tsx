import type { FC } from 'react';
import { useState } from 'react';

import { useSendRequest } from '../../shared/hooks/use-send-request';
import { Button } from '@ui/button/components/button';
import { Label } from '@ui/forms/components/label';
import { Modal } from '@ui/modal/components/modal';
import { Spinner } from '@ui/spinner/components/spinner';
import type { ReverseVoucherModalProps } from '../props/reverse-voucher-modal-props';
import type { ApiVoucher } from '../types/api-voucher';

/**
 * El comprobante original nunca se elimina ni se modifica más allá de su estado —
 * esto solo genera el reverso, igual que `reversar_comprobante.php` del legado.
 */
export const ReverseVoucherModal: FC<ReverseVoucherModalProps> = ({ isOpen, onClose, voucherId, onReversed }) => {
    const { sendRequest } = useSendRequest();

    const [motivo, setMotivo] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleConfirm = async () => {
        setError(null);
        setIsSubmitting(true);

        try {
            const respuesta = await sendRequest<{ comprobante: ApiVoucher }>(`/contabilidad/comprobantes/${voucherId}/reversar`, {
                method: 'POST',
                body: { motivo },
            });

            onReversed(respuesta.comprobante.id);
            setMotivo('');
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <Modal isOpen={isOpen} onClose={onClose} size="sm">
            <h4 className="mb-2 text-lg font-semibold text-gray-800 dark:text-white/90">Reversar comprobante</h4>
            <p className="mb-5 text-sm text-gray-500 dark:text-gray-400">
                El comprobante original no será eliminado; se creará un nuevo comprobante con las líneas invertidas.
            </p>

            {error && (
                <div className="mb-4 rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                    {error}
                </div>
            )}

            <Label htmlFor="motivo">Motivo del reverso</Label>
            <textarea
                id="motivo"
                required
                rows={3}
                value={motivo}
                onChange={(event) => setMotivo(event.target.value)}
                className="h-auto w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
            />

            <div className="mt-6 flex justify-end gap-3">
                <Button variant="outline" onClick={onClose} disabled={isSubmitting}>
                    Cancelar
                </Button>
                <Button
                    onClick={() => void handleConfirm()}
                    disabled={isSubmitting || motivo.trim() === ''}
                    startIcon={isSubmitting ? <Spinner size="sm" color="white" /> : undefined}
                >
                    {isSubmitting ? 'Reversando…' : 'Confirmar reverso'}
                </Button>
            </div>
        </Modal>
    );
};
