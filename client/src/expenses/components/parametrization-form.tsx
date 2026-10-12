import type { FC, SubmitEvent } from 'react';
import { useState } from 'react';

import { useSendRequest } from '../../shared/hooks/use-send-request';
import { Button } from '@ui/button/components/button';
import { DatePicker } from '@ui/forms/components/date-picker';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { Select } from '@ui/forms/components/select';
import { ToggleSwitchInput } from '@ui/forms/components/toggle-switch-input';
import { Spinner } from '@ui/spinner/components/spinner';
import type { ParametrizationFormProps } from '../props/parametrization-form-props';
import type { ApiParametrization } from '../types/api-parametrization';

export const ParametrizationForm: FC<ParametrizationFormProps> = ({ tiposGasto, cuentas, tiposComprobante, onCreated }) => {
    const { sendRequest } = useSendRequest();

    const tipoGastoOptions = tiposGasto.map((tipo) => ({ value: String(tipo.id), label: `${tipo.codigo} - ${tipo.nombre}` }));
    const cuentaOptions = cuentas.map((cuenta) => ({ value: String(cuenta.id), label: `${cuenta.codigo} - ${cuenta.nombre}` }));
    const tipoComprobanteOptions = tiposComprobante.map((tipo) => ({ value: String(tipo.id), label: `${tipo.codigo} - ${tipo.nombre}` }));

    const [tipoGastoId, setTipoGastoId] = useState('');
    const [fechaDesde, setFechaDesde] = useState(() => new Date().toISOString().slice(0, 10));
    const [tipoComprobanteId, setTipoComprobanteId] = useState('');
    const [cuentaGastoId, setCuentaGastoId] = useState('');
    const [cuentaCxpId, setCuentaCxpId] = useState('');
    const [cuentaIvaId, setCuentaIvaId] = useState('');
    const [tarifaIva, setTarifaIva] = useState('');

    const [aplicaRetefuente, setAplicaRetefuente] = useState(false);
    const [conceptoDian, setConceptoDian] = useState('');
    const [baseMinimaUvt, setBaseMinimaUvt] = useState('');
    const [tarifaRetefuenteNatural, setTarifaRetefuenteNatural] = useState('');
    const [tarifaRetefuenteJuridica, setTarifaRetefuenteJuridica] = useState('');
    const [cuentaRetefuenteId, setCuentaRetefuenteId] = useState('');

    const [aplicaReteica, setAplicaReteica] = useState(false);
    const [cuentaReteicaId, setCuentaReteicaId] = useState('');

    const [aplicaReteiva, setAplicaReteiva] = useState(false);
    const [tarifaReteiva, setTarifaReteiva] = useState('');
    const [cuentaReteivaId, setCuentaReteivaId] = useState('');

    const [error, setError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSubmit = async (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();
        setError(null);
        setIsSubmitting(true);

        try {
            const respuesta = await sendRequest<{ parametrizacion: ApiParametrization }>('/gastos/parametrizaciones', {
                method: 'POST',
                body: {
                    tipo_gasto_id: Number(tipoGastoId),
                    fecha_desde: fechaDesde,
                    tipo_comprobante_id: Number(tipoComprobanteId),
                    cuenta_gasto_id: Number(cuentaGastoId),
                    cuenta_cxp_id: Number(cuentaCxpId),
                    cuenta_iva_id: cuentaIvaId ? Number(cuentaIvaId) : null,
                    tarifa_iva: tarifaIva ? parseFloat(tarifaIva) : null,
                    aplica_retefuente: aplicaRetefuente,
                    concepto_dian: conceptoDian || null,
                    base_minima_uvt: baseMinimaUvt ? Number(baseMinimaUvt) : null,
                    tarifa_retefuente_persona_natural: aplicaRetefuente ? parseFloat(tarifaRetefuenteNatural) || 0 : null,
                    tarifa_retefuente_persona_juridica: aplicaRetefuente ? parseFloat(tarifaRetefuenteJuridica) || 0 : null,
                    cuenta_retefuente_id: aplicaRetefuente ? Number(cuentaRetefuenteId) : null,
                    aplica_reteica: aplicaReteica,
                    cuenta_reteica_id: aplicaReteica ? Number(cuentaReteicaId) : null,
                    aplica_reteiva: aplicaReteiva,
                    tarifa_reteiva: aplicaReteiva ? parseFloat(tarifaReteiva) || 0 : null,
                    cuenta_reteiva_id: aplicaReteiva ? Number(cuentaReteivaId) : null,
                },
            });

            onCreated(respuesta.parametrizacion);
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <form onSubmit={handleSubmit}>
            <div className="space-y-6">
                {error && (
                    <div className="rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                        {error}
                    </div>
                )}

                <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div>
                        <Label htmlFor="tipo-gasto">Tipo de gasto</Label>
                        <Select id="tipo-gasto" options={tipoGastoOptions} defaultValue={tipoGastoId} onChange={setTipoGastoId} />
                    </div>
                    <div>
                        <Label htmlFor="fecha-desde">Vigente desde</Label>
                        <DatePicker id="fecha-desde" name="fecha-desde" required value={fechaDesde} onChange={setFechaDesde} />
                    </div>
                    <div>
                        <Label htmlFor="tipo-comprobante">Tipo de comprobante a generar</Label>
                        <Select id="tipo-comprobante" options={tipoComprobanteOptions} defaultValue={tipoComprobanteId} onChange={setTipoComprobanteId} />
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-6 md:grid-cols-4">
                    <div>
                        <Label htmlFor="cuenta-gasto">Cuenta de gasto</Label>
                        <Select id="cuenta-gasto" options={cuentaOptions} placeholder="--" defaultValue={cuentaGastoId} onChange={setCuentaGastoId} />
                    </div>
                    <div>
                        <Label htmlFor="cuenta-cxp">Cuenta de cuentas por pagar</Label>
                        <Select id="cuenta-cxp" options={cuentaOptions} placeholder="--" defaultValue={cuentaCxpId} onChange={setCuentaCxpId} />
                    </div>
                    <div>
                        <Label htmlFor="cuenta-iva">Cuenta IVA descontable</Label>
                        <Select id="cuenta-iva" options={cuentaOptions} placeholder="--" defaultValue={cuentaIvaId} onChange={setCuentaIvaId} />
                    </div>
                    <div>
                        <Label htmlFor="tarifa-iva">Tarifa de IVA (%)</Label>
                        <Input
                            id="tarifa-iva"
                            name="tarifa-iva"
                            type="number"
                            step={0.001}
                            value={tarifaIva}
                            onChange={(event) => setTarifaIva(event.target.value)}
                        />
                    </div>
                </div>

                <div className="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                    <ToggleSwitchInput
                        id="aplica-retefuente"
                        label="Aplica ReteFuente"
                        checked={aplicaRetefuente}
                        onChange={setAplicaRetefuente}
                    />

                    {aplicaRetefuente && (
                        <div className="mt-4 grid grid-cols-1 gap-6 md:grid-cols-4">
                            <div>
                                <Label htmlFor="concepto-dian">Concepto DIAN</Label>
                                <Input id="concepto-dian" name="concepto-dian" value={conceptoDian} onChange={(event) => setConceptoDian(event.target.value)} />
                            </div>
                            <div>
                                <Label htmlFor="base-minima-uvt">Base mínima (UVT)</Label>
                                <Input
                                    id="base-minima-uvt"
                                    name="base-minima-uvt"
                                    type="number"
                                    value={baseMinimaUvt}
                                    onChange={(event) => setBaseMinimaUvt(event.target.value)}
                                />
                            </div>
                            <div>
                                <Label htmlFor="tarifa-retefuente-natural">Tarifa persona natural (%)</Label>
                                <Input
                                    id="tarifa-retefuente-natural"
                                    name="tarifa-retefuente-natural"
                                    type="number"
                                    step={0.001}
                                    required={aplicaRetefuente}
                                    value={tarifaRetefuenteNatural}
                                    onChange={(event) => setTarifaRetefuenteNatural(event.target.value)}
                                />
                            </div>
                            <div>
                                <Label htmlFor="tarifa-retefuente-juridica">Tarifa persona jurídica (%)</Label>
                                <Input
                                    id="tarifa-retefuente-juridica"
                                    name="tarifa-retefuente-juridica"
                                    type="number"
                                    step={0.001}
                                    required={aplicaRetefuente}
                                    value={tarifaRetefuenteJuridica}
                                    onChange={(event) => setTarifaRetefuenteJuridica(event.target.value)}
                                />
                            </div>
                            <div className="md:col-span-2">
                                <Label htmlFor="cuenta-retefuente">Cuenta ReteFuente por pagar</Label>
                                <Select
                                    id="cuenta-retefuente"
                                    options={cuentaOptions}
                                    placeholder="--"
                                    defaultValue={cuentaRetefuenteId}
                                    onChange={setCuentaRetefuenteId}
                                />
                            </div>
                        </div>
                    )}
                </div>

                <div className="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                    <ToggleSwitchInput id="aplica-reteica" label="Aplica ReteICA" checked={aplicaReteica} onChange={setAplicaReteica} />

                    {aplicaReteica && (
                        <div className="mt-4 grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div className="md:col-span-2">
                                <p className="mb-2 text-xs text-gray-500 dark:text-gray-400">
                                    La tarifa de ReteICA no se define aquí: sale de la tabla de tarifas por municipio y actividad económica.
                                </p>
                                <Label htmlFor="cuenta-reteica">Cuenta ReteICA por pagar</Label>
                                <Select id="cuenta-reteica" options={cuentaOptions} placeholder="--" defaultValue={cuentaReteicaId} onChange={setCuentaReteicaId} />
                            </div>
                        </div>
                    )}
                </div>

                <div className="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                    <ToggleSwitchInput id="aplica-reteiva" label="Aplica ReteIVA" checked={aplicaReteiva} onChange={setAplicaReteiva} />

                    {aplicaReteiva && (
                        <div className="mt-4 grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div>
                                <Label htmlFor="tarifa-reteiva">Tarifa de ReteIVA (%)</Label>
                                <Input
                                    id="tarifa-reteiva"
                                    name="tarifa-reteiva"
                                    type="number"
                                    step={0.001}
                                    required={aplicaReteiva}
                                    value={tarifaReteiva}
                                    onChange={(event) => setTarifaReteiva(event.target.value)}
                                />
                            </div>
                            <div>
                                <Label htmlFor="cuenta-reteiva">Cuenta ReteIVA por pagar</Label>
                                <Select id="cuenta-reteiva" options={cuentaOptions} placeholder="--" defaultValue={cuentaReteivaId} onChange={setCuentaReteivaId} />
                            </div>
                        </div>
                    )}
                </div>

                <div className="flex justify-end border-t border-gray-100 pt-6 dark:border-gray-800">
                    <Button
                        type="submit"
                        disabled={isSubmitting || !tipoGastoId || !tipoComprobanteId || !cuentaGastoId || !cuentaCxpId}
                        startIcon={isSubmitting ? <Spinner size="sm" color="white" /> : undefined}
                    >
                        {isSubmitting ? 'Guardando…' : 'Guardar parametrización'}
                    </Button>
                </div>
            </div>
        </form>
    );
};
