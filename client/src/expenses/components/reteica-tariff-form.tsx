import type { FC, SubmitEvent } from 'react';
import { useState } from 'react';

import { useSendRequest } from '../../shared/hooks/use-send-request';
import { Button } from '@ui/button/components/button';
import { DatePicker } from '@ui/forms/components/date-picker';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { Select } from '@ui/forms/components/select';
import { Spinner } from '@ui/spinner/components/spinner';
import type { ReteicaTariffFormProps } from '../props/reteica-tariff-form-props';
import type { ApiReteicaTariff } from '../types/api-reteica-tariff';

export const ReteicaTariffForm: FC<ReteicaTariffFormProps> = ({ municipios, actividades, onCreated }) => {
    const { sendRequest } = useSendRequest();

    const municipioOptions = municipios.map((municipio) => ({ value: String(municipio.id), label: `${municipio.codigo} - ${municipio.nombre}` }));
    const actividadOptions = actividades.map((actividad) => ({ value: String(actividad.id), label: `${actividad.codigo} - ${actividad.nombre}` }));

    const [municipioId, setMunicipioId] = useState('');
    const [actividadId, setActividadId] = useState('');
    const [fechaDesde, setFechaDesde] = useState(() => new Date().toISOString().slice(0, 10));
    const [tarifa, setTarifa] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSubmit = async (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();
        setError(null);
        setIsSubmitting(true);

        try {
            const respuesta = await sendRequest<{ tarifa: ApiReteicaTariff }>('/gastos/tarifas-reteica', {
                method: 'POST',
                body: {
                    municipio_id: Number(municipioId),
                    actividad_economica_id: Number(actividadId),
                    fecha_desde: fechaDesde,
                    tarifa: parseFloat(tarifa) || 0,
                },
            });

            onCreated(respuesta.tarifa);
            setTarifa('');
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

                <div className="grid grid-cols-1 gap-6 md:grid-cols-4">
                    <div>
                        <Label htmlFor="municipio">Municipio</Label>
                        <Select options={municipioOptions} defaultValue={municipioId} onChange={setMunicipioId} />
                    </div>

                    <div>
                        <Label htmlFor="actividad">Actividad económica</Label>
                        <Select options={actividadOptions} defaultValue={actividadId} onChange={setActividadId} />
                    </div>

                    <div>
                        <Label htmlFor="fecha-desde">Vigente desde</Label>
                        <DatePicker id="fecha-desde" name="fecha-desde" required value={fechaDesde} onChange={setFechaDesde} />
                    </div>

                    <div>
                        <Label htmlFor="tarifa">Tarifa (por mil)</Label>
                        <Input
                            id="tarifa"
                            name="tarifa"
                            type="number"
                            step={0.001}
                            required
                            value={tarifa}
                            onChange={(event) => setTarifa(event.target.value)}
                        />
                    </div>
                </div>

                <div className="flex justify-end border-t border-gray-100 pt-6 dark:border-gray-800">
                    <Button
                        type="submit"
                        disabled={isSubmitting || !municipioId || !actividadId}
                        startIcon={isSubmitting ? <Spinner size="sm" color="white" /> : undefined}
                    >
                        {isSubmitting ? 'Guardando…' : 'Guardar tarifa'}
                    </Button>
                </div>
            </div>
        </form>
    );
};
