import type { FC, SubmitEvent } from 'react';
import { useState } from 'react';

import { useSendRequest } from '../../shared/hooks/use-send-request';
import { Button } from '@ui/button/components/button';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { ToggleSwitchInput } from '@ui/forms/components/toggle-switch-input';
import { Spinner } from '@ui/spinner/components/spinner';
import type { BusinessDocumentTypeFormProps } from '../props/business-document-type-form-props';
import type { ApiBusinessDocumentType } from '../types/api-business-document-type';

export const BusinessDocumentTypeForm: FC<BusinessDocumentTypeFormProps> = ({ tipo, onSaved }) => {
    const { sendRequest } = useSendRequest();

    const [nombre, setNombre] = useState(tipo.nombre);
    const [creaSaldo, setCreaSaldo] = useState(tipo.crea_saldo);
    const [cruzaSaldo, setCruzaSaldo] = useState(tipo.cruza_saldo);
    const [activo, setActivo] = useState(tipo.activo);
    const [error, setError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSubmit = async (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();
        setError(null);
        setIsSubmitting(true);

        try {
            const respuesta = await sendRequest<{ tipo: ApiBusinessDocumentType }>(`/contabilidad/tipos-documento-negocio/${tipo.id}`, {
                method: 'PATCH',
                body: { nombre, crea_saldo: creaSaldo, cruza_saldo: cruzaSaldo, activo },
            });

            onSaved(respuesta.tipo);
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <form onSubmit={handleSubmit}>
            <div className="space-y-5">
                {error && (
                    <div className="rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                        {error}
                    </div>
                )}

                <div>
                    <Label htmlFor="nombre">Nombre</Label>
                    <Input id="nombre" name="nombre" required value={nombre} onChange={(event) => setNombre(event.target.value)} />
                </div>

                <div className="flex flex-wrap gap-8">
                    <ToggleSwitchInput id="crea-saldo" label="Crea saldo" checked={creaSaldo} onChange={setCreaSaldo} />
                    <ToggleSwitchInput id="cruza-saldo" label="Cruza saldo" checked={cruzaSaldo} onChange={setCruzaSaldo} />
                    <ToggleSwitchInput id="activo" label="Activo" checked={activo} onChange={setActivo} />
                </div>

                <p className="text-xs text-gray-400 dark:text-gray-500">
                    Si desactivas «crea saldo» y «cruza saldo» a la vez, o los inviertes respecto a lo que el módulo realmente hace, la
                    operación correspondiente (facturar, cobrar, pagar, causar) quedará bloqueada hasta que la configuración vuelva a
                    coincidir con el comportamiento real.
                </p>

                <div className="flex justify-end border-t border-gray-100 pt-6 dark:border-gray-800">
                    <Button type="submit" disabled={isSubmitting} startIcon={isSubmitting ? <Spinner size="sm" color="white" /> : undefined}>
                        {isSubmitting ? 'Guardando…' : 'Guardar cambios'}
                    </Button>
                </div>
            </div>
        </form>
    );
};
