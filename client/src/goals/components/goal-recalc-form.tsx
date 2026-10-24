import type { FC, SubmitEvent } from 'react';
import { useState } from 'react';

import { useSendRequest } from '../../shared/hooks/use-send-request';
import { Button } from '@ui/button/components/button';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { Spinner } from '@ui/spinner/components/spinner';
import { GOAL_TYPE_LABELS } from '../constants/goal-status';
import type { GoalRecalcFormProps } from '../props/goal-recalc-form-props';

export const GoalRecalcForm: FC<GoalRecalcFormProps> = ({ periodo, tipo, onRecalculated }) => {
    const { sendRequest } = useSendRequest();

    const [motivo, setMotivo] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSubmit = async (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();
        setError(null);
        setIsSubmitting(true);

        try {
            await sendRequest('/metas/cierres/recalcular', { method: 'POST', body: { periodo, tipo, motivo } });
            setMotivo('');
            onRecalculated();
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-4">
            {error && (
                <div className="rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                    {error}
                </div>
            )}

            <div>
                <Label htmlFor={`motivo-${tipo}`}>Motivo del recálculo de {GOAL_TYPE_LABELS[tipo].toLowerCase()}</Label>
                <Input id={`motivo-${tipo}`} name="motivo" required minLength={10} maxLength={255} value={motivo} onChange={(event) => setMotivo(event.target.value)} />
            </div>

            <div className="flex justify-end">
                <Button type="submit" variant="outline" disabled={isSubmitting} startIcon={isSubmitting ? <Spinner size="sm" /> : undefined}>
                    {isSubmitting ? 'Recalculando…' : 'Recalcular período cerrado'}
                </Button>
            </div>
        </form>
    );
};
