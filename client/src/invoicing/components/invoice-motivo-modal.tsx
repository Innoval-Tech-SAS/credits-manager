import type { FC } from 'react';
import { useState } from 'react';

import { Button } from '@ui/button/components/button';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { Modal } from '@ui/modal/components/modal';
import type { InvoiceMotivoModalProps } from '../props/invoice-motivo-modal-props';

/** Pide el motivo obligatorio de una corrección (anular una factura o reversar un cobro). */
export const InvoiceMotivoModal: FC<InvoiceMotivoModalProps> = ({ isOpen, title, description, confirmLabel, isSubmitting, onClose, onConfirm }) => (
    <Modal isOpen={isOpen} onClose={onClose} size="md">
        {/* El formulario se monta al abrir: su motivo arranca vacío en cada apertura. */}
        {isOpen && (
            <FormularioMotivo
                title={title}
                description={description}
                confirmLabel={confirmLabel}
                isSubmitting={isSubmitting}
                onClose={onClose}
                onConfirm={onConfirm}
            />
        )}
    </Modal>
);

type FormularioMotivoProps = Omit<InvoiceMotivoModalProps, 'isOpen'>;

const FormularioMotivo: FC<FormularioMotivoProps> = ({ title, description, confirmLabel, isSubmitting, onClose, onConfirm }) => {
    const [motivo, setMotivo] = useState('');
    const motivoLimpio = motivo.trim();

    return (
        <div className="flex flex-col gap-5">
            <div className="flex flex-col gap-2 pr-10">
                <h3 className="text-lg font-semibold text-gray-800 dark:text-white/90">{title}</h3>
                <p className="text-sm text-gray-500 dark:text-gray-400">{description}</p>
            </div>

            <div>
                <Label htmlFor="motivo-correccion">Motivo</Label>
                <Input id="motivo-correccion" value={motivo} maxLength={500} onChange={(event) => setMotivo(event.target.value)} />
            </div>

            <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Button variant="outline" onClick={onClose} disabled={isSubmitting}>
                    Cancelar
                </Button>
                <Button disabled={isSubmitting || motivoLimpio === ''} onClick={() => onConfirm(motivoLimpio)}>
                    {isSubmitting ? 'Procesando…' : confirmLabel}
                </Button>
            </div>
        </div>
    );
};
