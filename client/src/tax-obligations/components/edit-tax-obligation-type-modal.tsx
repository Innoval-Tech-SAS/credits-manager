import type { FC, SubmitEvent } from 'react';
import { useState } from 'react';

import { Tag } from 'lucide-react';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import { Button } from '@ui/button/components/button';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { Select } from '@ui/forms/components/select';
import { ToggleSwitchInput } from '@ui/forms/components/toggle-switch-input';
import { Modal } from '@ui/modal/components/modal';
import { Spinner } from '@ui/spinner/components/spinner';
import { TAX_OBLIGATION_PERIODICITY_OPTIONS } from '../constants/tax-obligation-periodicity-options';
import type { ApiTaxObligationPeriodicity } from '../types/api-tax-obligation-type';
import type { EditTaxObligationTypeModalProps } from '../props/edit-tax-obligation-type-modal-props';

export const EditTaxObligationTypeModal: FC<EditTaxObligationTypeModalProps> = ({ tipo, onClose, onSaved }) => {
    const { sendRequest } = useSendRequest();

    const [nombre, setNombre] = useState(tipo.nombre);
    const [periodicidad, setPeriodicidad] = useState<ApiTaxObligationPeriodicity>(tipo.periodicidad);
    const [diasAlertaPrevia, setDiasAlertaPrevia] = useState(String(tipo.dias_alerta_previa));
    const [activo, setActivo] = useState(tipo.activo);
    const [error, setError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSubmit = async (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();
        setError(null);
        setIsSubmitting(true);

        try {
            await sendRequest(`/obligaciones-tributarias/tipos/${tipo.id}`, {
                method: 'PUT',
                body: { nombre, periodicidad, dias_alerta_previa: Number(diasAlertaPrevia), activo },
            });

            onSaved();
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <Modal isOpen onClose={onClose} size="sm">
            <h4 className="mb-6 text-lg font-semibold text-gray-800 dark:text-white/90">Editar tipo de obligación</h4>

            <form onSubmit={handleSubmit}>
                <div className="space-y-5">
                    {error && (
                        <div className="rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                            {error}
                        </div>
                    )}

                    <div>
                        <Label htmlFor="edit-tipo-nombre">Nombre</Label>
                        <Input
                            id="edit-tipo-nombre"
                            required
                            maxLength={120}
                            startIcon={<Tag className="size-4" />}
                            value={nombre}
                            onChange={(event) => setNombre(event.target.value)}
                        />
                    </div>

                    <div>
                        <Label htmlFor="edit-tipo-periodicidad">Periodicidad</Label>
                        <Select
                            id="edit-tipo-periodicidad"
                            options={TAX_OBLIGATION_PERIODICITY_OPTIONS}
                            defaultValue={periodicidad}
                            onChange={(value) => setPeriodicidad(value as ApiTaxObligationPeriodicity)}
                        />
                    </div>

                    <div>
                        <Label htmlFor="edit-tipo-dias-alerta">Días de alerta previa</Label>
                        <Input
                            id="edit-tipo-dias-alerta"
                            type="number"
                            min="0"
                            max="365"
                            required
                            value={diasAlertaPrevia}
                            onChange={(event) => setDiasAlertaPrevia(event.target.value)}
                        />
                    </div>

                    <ToggleSwitchInput id="edit-tipo-activo" label="Activo" checked={activo} onChange={setActivo} />

                    <div className="flex justify-end gap-3 pt-2">
                        <Button variant="outline" type="button" onClick={onClose} disabled={isSubmitting}>
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={isSubmitting} startIcon={isSubmitting ? <Spinner size="sm" color="white" /> : undefined}>
                            {isSubmitting ? 'Guardando…' : 'Guardar'}
                        </Button>
                    </div>
                </div>
            </form>
        </Modal>
    );
};
