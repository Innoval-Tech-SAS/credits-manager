import type { FC, SubmitEvent } from 'react';
import { useState } from 'react';
import { Hash, MapPin } from 'lucide-react';

import { useSendRequest } from '../../shared/hooks/use-send-request';
import { Button } from '@ui/button/components/button';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { ToggleSwitchInput } from '@ui/forms/components/toggle-switch-input';
import { Spinner } from '@ui/spinner/components/spinner';
import type { MunicipalityFormProps } from '../props/municipality-form-props';
import type { ApiMunicipality } from '../types/api-municipality';
import { StateSelector } from './state-selector';

export const MunicipalityForm: FC<MunicipalityFormProps> = ({ onCreated }) => {
    const { sendRequest } = useSendRequest();

    const [stateId, setStateId] = useState('');
    const [code, setCode] = useState('');
    const [name, setName] = useState('');
    const [isCapital, setIsCapital] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSubmit = async (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();
        setError(null);
        setIsSubmitting(true);

        try {
            const response = await sendRequest<{ municipio: ApiMunicipality }>('/geografia/municipios', {
                method: 'POST',
                body: { estado_id: Number(stateId), codigo: code, nombre: name, es_capital: isCapital },
            });

            onCreated(response.municipio);
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
                    <StateSelector onChange={setStateId} />
                </div>

                <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div>
                        <Label htmlFor="codigo">Código DANE</Label>
                        <Input
                            id="codigo"
                            name="codigo"
                            required
                            maxLength={10}
                            startIcon={<Hash className="size-4" />}
                            value={code}
                            onChange={(event) => setCode(event.target.value)}
                        />
                    </div>

                    <div className="md:col-span-2">
                        <Label htmlFor="nombre">Nombre</Label>
                        <Input
                            id="nombre"
                            name="nombre"
                            required
                            maxLength={150}
                            startIcon={<MapPin className="size-4" />}
                            value={name}
                            onChange={(event) => setName(event.target.value)}
                        />
                    </div>
                </div>

                <ToggleSwitchInput id="es-capital" label="Es capital del departamento" checked={isCapital} onChange={setIsCapital} />

                <div className="flex justify-end border-t border-gray-100 pt-6 dark:border-gray-800">
                    <Button
                        type="submit"
                        disabled={isSubmitting || !stateId}
                        startIcon={isSubmitting ? <Spinner size="sm" color="white" /> : undefined}
                    >
                        {isSubmitting ? 'Creando…' : 'Crear municipio'}
                    </Button>
                </div>
            </div>
        </form>
    );
};
