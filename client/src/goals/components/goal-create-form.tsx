import type { FC, SubmitEvent } from 'react';
import { useState } from 'react';

import { useSendRequest } from '../../shared/hooks/use-send-request';
import { Button } from '@ui/button/components/button';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { Select } from '@ui/forms/components/select';
import { Spinner } from '@ui/spinner/components/spinner';
import { GOAL_TYPE_LABELS } from '../constants/goal-status';
import type { GoalCreateFormProps } from '../props/goal-create-form-props';
import type { ApiGoalType } from '../types/api-goals-period';

export const GoalCreateForm: FC<GoalCreateFormProps> = ({ periodo, tiposDisponibles, onCreated }) => {
    const { sendRequest } = useSendRequest();

    const [tipo, setTipo] = useState<ApiGoalType>(tiposDisponibles[0]);
    const [valor, setValor] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSubmit = async (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();
        setError(null);
        setIsSubmitting(true);

        try {
            await sendRequest('/metas', {
                method: 'POST',
                body: { periodo, tipo, valor_objetivo: valor },
            });

            setValor('');
            onCreated();
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-5">
            {error && (
                <div className="rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                    {error}
                </div>
            )}

            <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div>
                    <Label htmlFor="meta-tipo">Tipo de meta</Label>
                    <Select
                        key={tiposDisponibles.join('-')}
                        options={tiposDisponibles.map((disponible) => ({ value: disponible, label: GOAL_TYPE_LABELS[disponible] }))}
                        defaultValue={tiposDisponibles[0]}
                        placeholderSelectable={false}
                        onChange={(value) => setTipo(value as ApiGoalType)}
                    />
                </div>

                <div>
                    <Label htmlFor="meta-valor">Valor objetivo</Label>
                    <Input id="meta-valor" name="valor_objetivo" type="number" step={0.01} min="0.01" required value={valor} onChange={(event) => setValor(event.target.value)} />
                </div>
            </div>

            <div className="flex justify-end border-t border-gray-100 pt-6 dark:border-gray-800">
                <Button type="submit" disabled={isSubmitting} startIcon={isSubmitting ? <Spinner size="sm" color="white" /> : undefined}>
                    {isSubmitting ? 'Guardando…' : 'Crear meta'}
                </Button>
            </div>
        </form>
    );
};
