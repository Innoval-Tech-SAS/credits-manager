import type { FC, SubmitEvent } from 'react';
import { useState } from 'react';

import { useSendRequest } from '../../shared/hooks/use-send-request';
import { Button } from '@ui/button/components/button';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { ToggleSwitchInput } from '@ui/forms/components/toggle-switch-input';
import { Spinner } from '@ui/spinner/components/spinner';
import type { ExpenseTypeFormProps } from '../props/expense-type-form-props';
import type { ApiExpenseType } from '../types/api-expense-type';

export const ExpenseTypeForm: FC<ExpenseTypeFormProps> = ({ onCreated }) => {
    const { sendRequest } = useSendRequest();

    const [codigo, setCodigo] = useState('');
    const [nombre, setNombre] = useState('');
    const [requiereTercero, setRequiereTercero] = useState(true);
    const [aplicaIva, setAplicaIva] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSubmit = async (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();
        setError(null);
        setIsSubmitting(true);

        try {
            const respuesta = await sendRequest<{ tipo_gasto: ApiExpenseType }>('/gastos/tipos-gasto', {
                method: 'POST',
                body: { codigo, nombre, requiere_tercero: requiereTercero, aplica_iva: aplicaIva },
            });

            onCreated(respuesta.tipo_gasto);
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
                        <Input
                            id="codigo"
                            name="codigo"
                            placeholder="ARR, HON, SERV…"
                            required
                            value={codigo}
                            onChange={(event) => setCodigo(event.target.value)}
                        />
                    </div>

                    <div className="md:col-span-2">
                        <Label htmlFor="nombre">Nombre</Label>
                        <Input id="nombre" name="nombre" required value={nombre} onChange={(event) => setNombre(event.target.value)} />
                    </div>
                </div>

                <div className="flex flex-wrap gap-8">
                    <ToggleSwitchInput
                        id="requiere-tercero"
                        label="Requiere tercero"
                        checked={requiereTercero}
                        onChange={setRequiereTercero}
                    />
                    <ToggleSwitchInput id="aplica-iva" label="Aplica IVA" checked={aplicaIva} onChange={setAplicaIva} />
                </div>

                <div className="flex justify-end border-t border-gray-100 pt-6 dark:border-gray-800">
                    <Button type="submit" disabled={isSubmitting} startIcon={isSubmitting ? <Spinner size="sm" color="white" /> : undefined}>
                        {isSubmitting ? 'Creando…' : 'Crear tipo de gasto'}
                    </Button>
                </div>
            </div>
        </form>
    );
};
