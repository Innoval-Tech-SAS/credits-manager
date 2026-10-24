import type { FC, SubmitEvent } from 'react';
import { useState } from 'react';
import { Hash } from 'lucide-react';

import { useSendRequest } from '../../shared/hooks/use-send-request';
import { Button } from '@ui/button/components/button';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { ToggleSwitchInput } from '@ui/forms/components/toggle-switch-input';
import { Spinner } from '@ui/spinner/components/spinner';
import type { ExpenseTypeFormProps } from '../props/expense-type-form-props';
import type { ApiExpenseType } from '../types/api-expense-type';

export const ExpenseTypeForm: FC<ExpenseTypeFormProps> = ({ expenseTypeId, initialData, onSaved }) => {
    const { sendRequest } = useSendRequest();
    const isEditMode = expenseTypeId !== undefined;

    const [codigo, setCodigo] = useState(initialData?.codigo ?? '');
    const [nombre, setNombre] = useState(initialData?.nombre ?? '');
    const [requiereTercero, setRequiereTercero] = useState(initialData?.requiere_tercero ?? true);
    const [aplicaIva, setAplicaIva] = useState(initialData?.aplica_iva ?? false);
    const [activo, setActivo] = useState(initialData?.activo ?? true);
    const [error, setError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSubmit = async (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();
        setError(null);
        setIsSubmitting(true);

        try {
            const respuesta = isEditMode
                ? await sendRequest<{ tipo_gasto: ApiExpenseType }>(`/gastos/tipos-gasto/${expenseTypeId}`, {
                      method: 'PATCH',
                      body: {
                          codigo,
                          nombre,
                          requiere_tercero: requiereTercero,
                          aplica_iva: aplicaIva,
                          activo,
                      },
                  })
                : await sendRequest<{ tipo_gasto: ApiExpenseType }>('/gastos/tipos-gasto', {
                      method: 'POST',
                      body: { codigo, nombre, requiere_tercero: requiereTercero, aplica_iva: aplicaIva },
                  });

            onSaved(respuesta.tipo_gasto);

            if (!isEditMode) {
                setCodigo('');
                setNombre('');
            }
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
                            startIcon={<Hash className="size-4" />}
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
                    {isEditMode && <ToggleSwitchInput id="activo" label="Activo" checked={activo} onChange={setActivo} />}
                </div>

                <div className="flex justify-end border-t border-gray-100 pt-6 dark:border-gray-800">
                    <Button type="submit" disabled={isSubmitting} startIcon={isSubmitting ? <Spinner size="sm" color="white" /> : undefined}>
                        {isSubmitting ? 'Guardando…' : isEditMode ? 'Actualizar tipo de gasto' : 'Crear tipo de gasto'}
                    </Button>
                </div>
            </div>
        </form>
    );
};
