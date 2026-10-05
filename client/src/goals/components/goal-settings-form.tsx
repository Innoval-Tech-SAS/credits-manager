import type { FC, SubmitEvent } from 'react';
import { useState } from 'react';

import { useSendRequest } from '../../shared/hooks/use-send-request';
import { Button } from '@ui/button/components/button';
import { Checkbox } from '@ui/forms/components/checkbox';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { Select } from '@ui/forms/components/select';
import { Spinner } from '@ui/spinner/components/spinner';
import type { GoalSettingsFormProps } from '../props/goal-settings-form-props';
import type { ApiGoalSettings } from '../types/api-goal-settings';

const OPCIONES_REFINANCIACIONES = [
    { value: 'excluir', label: 'Excluir (no cuenta)' },
    { value: 'solo_dinero_nuevo', label: 'Solo dinero nuevo' },
    { value: 'total', label: 'Valor total del crédito' },
];

const OPCIONES_BASE_RECAUDO = [
    { value: 'total', label: 'Total pagado' },
    { value: 'solo_capital', label: 'Solo capital' },
    { value: 'solo_interes', label: 'Solo interés' },
];

const OPCIONES_DIA_CIERRE = [
    { value: 'ultimo_dia', label: 'Último día del mes' },
    { value: 'primer_dia_siguiente', label: 'Primer día del mes siguiente' },
];

export const GoalSettingsForm: FC<GoalSettingsFormProps> = ({ configuracion, onSaved }) => {
    const { sendRequest } = useSendRequest();

    const [valores, setValores] = useState<ApiGoalSettings>(configuracion);
    const [error, setError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const actualizar = <Clave extends keyof ApiGoalSettings>(clave: Clave, valor: ApiGoalSettings[Clave]) => {
        setValores((actuales) => ({ ...actuales, [clave]: valor }));
    };

    const handleSubmit = async (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();
        setError(null);
        setIsSubmitting(true);

        try {
            const respuesta = await sendRequest<{ parametros: ApiGoalSettings }>('/metas/configuracion', {
                method: 'PUT',
                body: valores,
            });

            onSaved(respuesta.parametros);
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            {error && (
                <div className="rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                    {error}
                </div>
            )}

            <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div>
                    <Label htmlFor="tolerancia">Tolerancia de riesgo (puntos)</Label>
                    <Input id="tolerancia" name="tolerancia_riesgo_puntos" type="number" min="0" max="100" required value={String(valores.tolerancia_riesgo_puntos)} onChange={(event) => actualizar('tolerancia_riesgo_puntos', Number(event.target.value))} />
                </div>

                <div>
                    <Label htmlFor="parcial">Cumplimiento parcial mínimo (%)</Label>
                    <Input id="parcial" name="cumplimiento_parcial_minimo" type="number" min="0" max="100" required value={String(valores.cumplimiento_parcial_minimo)} onChange={(event) => actualizar('cumplimiento_parcial_minimo', Number(event.target.value))} />
                </div>

                <div>
                    <Label htmlFor="umbral">Umbral de meta alcanzada (%)</Label>
                    <Input id="umbral" name="umbral_alcanzada" type="number" min="1" max="200" required value={String(valores.umbral_alcanzada)} onChange={(event) => actualizar('umbral_alcanzada', Number(event.target.value))} />
                </div>

                <div>
                    <Label>Refinanciaciones en la colocación</Label>
                    <Select options={OPCIONES_REFINANCIACIONES} defaultValue={valores.modo_colocacion_refinanciaciones} onChange={(value) => actualizar('modo_colocacion_refinanciaciones', value as ApiGoalSettings['modo_colocacion_refinanciaciones'])} />
                </div>

                <div>
                    <Label>Base del recaudo</Label>
                    <Select options={OPCIONES_BASE_RECAUDO} defaultValue={valores.base_recaudo} onChange={(value) => actualizar('base_recaudo', value as ApiGoalSettings['base_recaudo'])} />
                </div>

                <div>
                    <Label>Día de cierre</Label>
                    <Select options={OPCIONES_DIA_CIERRE} defaultValue={valores.dia_de_cierre} onChange={(value) => actualizar('dia_de_cierre', value as ApiGoalSettings['dia_de_cierre'])} />
                </div>
            </div>

            <div className="space-y-4 border-t border-gray-100 pt-6 dark:border-gray-800">
                <Checkbox label="Alerta cuando una meta se alcanza en el período abierto" checked={valores.alerta_alcanzada_activa} onChange={(checked) => actualizar('alerta_alcanzada_activa', checked)} />
                <Checkbox label="La distribución por asesor debe sumar exactamente la meta" checked={valores.distribucion_obligatoria_completa} onChange={(checked) => actualizar('distribucion_obligatoria_completa', checked)} />
                <Checkbox label="Mostrar al asesor la meta de empresa (sin el detalle de otros asesores)" checked={valores.meta_consolidada_visible_asesor} onChange={(checked) => actualizar('meta_consolidada_visible_asesor', checked)} />
            </div>

            <div className="flex justify-end border-t border-gray-100 pt-6 dark:border-gray-800">
                <Button type="submit" disabled={isSubmitting} startIcon={isSubmitting ? <Spinner size="sm" color="white" /> : undefined}>
                    {isSubmitting ? 'Guardando…' : 'Guardar parámetros'}
                </Button>
            </div>
        </form>
    );
};
