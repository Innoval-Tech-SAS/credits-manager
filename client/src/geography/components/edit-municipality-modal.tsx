import type { FC, SubmitEvent } from 'react';
import { useState } from 'react';

import { useSendRequest } from '../../shared/hooks/use-send-request';
import { Button } from '@ui/button/components/button';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { ToggleSwitchInput } from '@ui/forms/components/toggle-switch-input';
import { Modal } from '@ui/modal/components/modal';
import { Spinner } from '@ui/spinner/components/spinner';
import type { EditMunicipalityModalProps } from '../props/edit-municipality-modal-props';

export const EditMunicipalityModal: FC<EditMunicipalityModalProps> = ({ municipality, onClose, onSaved }) => {
    const { sendRequest } = useSendRequest();

    const [code, setCode] = useState(municipality.codigo);
    const [name, setName] = useState(municipality.nombre);
    const [isCapital, setIsCapital] = useState(municipality.es_capital);
    const [isActive, setIsActive] = useState(municipality.activo);
    const [error, setError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSubmit = async (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();
        setError(null);
        setIsSubmitting(true);

        try {
            await sendRequest(`/geografia/municipios/${municipality.id}`, {
                method: 'PATCH',
                body: { codigo: code, nombre: name, es_capital: isCapital, activo: isActive },
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
            <h4 className="mb-6 text-lg font-semibold text-gray-800 dark:text-white/90">Editar municipio</h4>

            <form onSubmit={handleSubmit}>
                <div className="space-y-5">
                    {error && (
                        <div className="rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                            {error}
                        </div>
                    )}

                    <div>
                        <Label htmlFor="edit-codigo">Código DANE</Label>
                        <Input id="edit-codigo" name="edit-codigo" required maxLength={10} value={code} onChange={(event) => setCode(event.target.value)} />
                    </div>

                    <div>
                        <Label htmlFor="edit-nombre">Nombre</Label>
                        <Input id="edit-nombre" name="edit-nombre" required maxLength={150} value={name} onChange={(event) => setName(event.target.value)} />
                    </div>

                    <ToggleSwitchInput id="edit-es-capital" label="Es capital del departamento" checked={isCapital} onChange={setIsCapital} />
                    <ToggleSwitchInput id="edit-activo" label="Disponible para las empresas" checked={isActive} onChange={setIsActive} />

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
