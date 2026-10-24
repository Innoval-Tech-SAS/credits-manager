import type { FC, SubmitEvent } from 'react';
import { useState } from 'react';

import { formatMoney } from '../../shared/utils/format-money';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import { Button } from '@ui/button/components/button';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { Spinner } from '@ui/spinner/components/spinner';
import type { GoalDistributionFormProps } from '../props/goal-distribution-form-props';

export const GoalDistributionForm: FC<GoalDistributionFormProps> = ({ meta, asesores, onSaved }) => {
    const { sendRequest } = useSendRequest();

    const [valores, setValores] = useState<Record<number, string>>(() =>
        Object.fromEntries(meta.asesores.map((fila) => [fila.usuario_id, fila.cumplimiento.objetivo])),
    );
    const [error, setError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const sumaAsignada = Object.values(valores).reduce((total, valor) => total + (parseFloat(valor) || 0), 0);

    const handleSubmit = async (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();
        setError(null);
        setIsSubmitting(true);

        const distribucion = Object.entries(valores)
            .filter(([, valor]) => (parseFloat(valor) || 0) > 0)
            .map(([usuarioId, valor]) => ({ usuario_id: Number(usuarioId), valor_objetivo: valor }));

        try {
            await sendRequest(`/metas/${meta.id}/asesores`, { method: 'PUT', body: { asesores: distribucion } });
            onSaved();
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
                {asesores.map((asesor) => (
                    <div key={asesor.id}>
                        <Label htmlFor={`asesor-${asesor.id}`}>{asesor.nombre}</Label>
                        <Input
                            id={`asesor-${asesor.id}`}
                            name={`asesor-${asesor.id}`}
                            type="number"
                            step={0.01}
                            min="0"
                            value={valores[asesor.id] ?? ''}
                            onChange={(event) => setValores((actuales) => ({ ...actuales, [asesor.id]: event.target.value }))}
                        />
                    </div>
                ))}
            </div>

            <p className="text-theme-xs text-gray-500 dark:text-gray-400">
                Asignado: {formatMoney(sumaAsignada)} de {formatMoney(meta.valor_objetivo)}. Los asesores sin valor quedan fuera de la distribución.
            </p>

            <div className="flex justify-end border-t border-gray-100 pt-6 dark:border-gray-800">
                <Button type="submit" disabled={isSubmitting} startIcon={isSubmitting ? <Spinner size="sm" color="white" /> : undefined}>
                    {isSubmitting ? 'Guardando…' : 'Guardar distribución'}
                </Button>
            </div>
        </form>
    );
};
