import type { FC, SubmitEvent } from 'react';
import { useState } from 'react';

import { useSendRequest } from '../../shared/hooks/use-send-request';
import { Button } from '@ui/button/components/button';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { Spinner } from '@ui/spinner/components/spinner';
import type { MunicipalityFormProps } from '../props/municipality-form-props';
import type { ApiMunicipality } from '../types/api-municipality';

export const MunicipalityForm: FC<MunicipalityFormProps> = ({ onCreated }) => {
    const { sendRequest } = useSendRequest();

    const [codigo, setCodigo] = useState('');
    const [nombre, setNombre] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSubmit = async (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();
        setError(null);
        setIsSubmitting(true);

        try {
            const respuesta = await sendRequest<{ municipio: ApiMunicipality }>('/gastos/municipios', {
                method: 'POST',
                body: { codigo, nombre },
            });

            onCreated(respuesta.municipio);
            setCodigo('');
            setNombre('');
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

                <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div>
                        <Label htmlFor="codigo">Código</Label>
                        <Input id="codigo" name="codigo" required value={codigo} onChange={(event) => setCodigo(event.target.value)} />
                    </div>

                    <div className="md:col-span-2">
                        <Label htmlFor="nombre">Nombre</Label>
                        <Input id="nombre" name="nombre" required value={nombre} onChange={(event) => setNombre(event.target.value)} />
                    </div>
                </div>

                <div className="flex justify-end border-t border-gray-100 pt-6 dark:border-gray-800">
                    <Button type="submit" disabled={isSubmitting} startIcon={isSubmitting ? <Spinner size="sm" color="white" /> : undefined}>
                        {isSubmitting ? 'Creando…' : 'Crear municipio'}
                    </Button>
                </div>
            </div>
        </form>
    );
};
