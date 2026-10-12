import type { FC } from 'react';
import { useState } from 'react';

import { useSendRequest } from '../../shared/hooks/use-send-request';
import { Button } from '@ui/button/components/button';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { Select } from '@ui/forms/components/select';
import { ToggleSwitchInput } from '@ui/forms/components/toggle-switch-input';
import { Spinner } from '@ui/spinner/components/spinner';
import type { ConceptTaxSettingsFormProps } from '../props/concept-tax-settings-form-props';
import type { ApiConceptTaxSettings } from '../types/api-invoice';

/** Una fila de `parametrizaciones_concepto_facturacion`. La tarifa de ReteICA no se define aquí: sale de la tabla de tarifas por municipio y actividad económica. */
export const ConceptTaxSettingsForm: FC<ConceptTaxSettingsFormProps> = ({ parametrizacion, cuentas, onSaved }) => {
    const { sendRequest, isLoading } = useSendRequest();

    const cuentaOptions = cuentas.map((cuenta) => ({ value: String(cuenta.id), label: `${cuenta.codigo} - ${cuenta.nombre}` }));

    const [tarifaIva, setTarifaIva] = useState(parametrizacion.tarifa_iva ?? '');
    const [cuentaIvaId, setCuentaIvaId] = useState(parametrizacion.cuenta_iva_id ? String(parametrizacion.cuenta_iva_id) : '');

    const [aplicaRetefuente, setAplicaRetefuente] = useState(parametrizacion.aplica_retefuente);
    const [baseMinimaUvt, setBaseMinimaUvt] = useState(parametrizacion.base_minima_uvt ? String(parametrizacion.base_minima_uvt) : '');
    const [tarifaRetefuente, setTarifaRetefuente] = useState(parametrizacion.tarifa_retefuente ?? '');
    const [cuentaRetefuenteId, setCuentaRetefuenteId] = useState(parametrizacion.cuenta_retefuente_id ? String(parametrizacion.cuenta_retefuente_id) : '');

    const [aplicaReteica, setAplicaReteica] = useState(parametrizacion.aplica_reteica);
    const [cuentaReteicaId, setCuentaReteicaId] = useState(parametrizacion.cuenta_reteica_id ? String(parametrizacion.cuenta_reteica_id) : '');

    const [aplicaReteiva, setAplicaReteiva] = useState(parametrizacion.aplica_reteiva);
    const [tarifaReteiva, setTarifaReteiva] = useState(parametrizacion.tarifa_reteiva ?? '');
    const [cuentaReteivaId, setCuentaReteivaId] = useState(parametrizacion.cuenta_reteiva_id ? String(parametrizacion.cuenta_reteiva_id) : '');

    const [error, setError] = useState<string | null>(null);

    const guardar = async () => {
        setError(null);

        try {
            const respuesta = await sendRequest<{ parametrizacion: ApiConceptTaxSettings }>(
                `/facturacion/parametrizacion-conceptos/${parametrizacion.tipo_concepto}`,
                {
                    method: 'PUT',
                    body: {
                        tarifa_iva: tarifaIva ? parseFloat(tarifaIva) : null,
                        cuenta_iva_id: cuentaIvaId ? Number(cuentaIvaId) : null,
                        aplica_retefuente: aplicaRetefuente,
                        base_minima_uvt: baseMinimaUvt ? Number(baseMinimaUvt) : null,
                        tarifa_retefuente: aplicaRetefuente ? parseFloat(tarifaRetefuente) || 0 : null,
                        cuenta_retefuente_id: aplicaRetefuente ? Number(cuentaRetefuenteId) : null,
                        aplica_reteica: aplicaReteica,
                        cuenta_reteica_id: aplicaReteica ? Number(cuentaReteicaId) : null,
                        aplica_reteiva: aplicaReteiva,
                        tarifa_reteiva: aplicaReteiva ? parseFloat(tarifaReteiva) || 0 : null,
                        cuenta_reteiva_id: aplicaReteiva ? Number(cuentaReteivaId) : null,
                    },
                },
            );

            onSaved(respuesta.parametrizacion);
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        }
    };

    return (
        <div className="space-y-6">
            {error && (
                <div className="rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                    {error}
                </div>
            )}

            <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div>
                    <Label htmlFor={`tarifa-iva-${parametrizacion.tipo_concepto}`}>Tarifa de IVA (%)</Label>
                    <Input
                        id={`tarifa-iva-${parametrizacion.tipo_concepto}`}
                        type="number"
                        step={0.001}
                        value={tarifaIva}
                        onChange={(event) => setTarifaIva(event.target.value)}
                    />
                </div>
                <div>
                    <Label htmlFor={`cuenta-iva-${parametrizacion.tipo_concepto}`}>Cuenta IVA generado</Label>
                    <Select options={cuentaOptions} placeholder="--" defaultValue={cuentaIvaId} onChange={setCuentaIvaId} />
                </div>
            </div>

            <div className="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                <ToggleSwitchInput
                    id={`aplica-retefuente-${parametrizacion.tipo_concepto}`}
                    label="Aplica ReteFuente"
                    checked={aplicaRetefuente}
                    onChange={setAplicaRetefuente}
                />

                {aplicaRetefuente && (
                    <div className="mt-4 grid grid-cols-1 gap-6 md:grid-cols-3">
                        <div>
                            <Label htmlFor={`base-minima-uvt-${parametrizacion.tipo_concepto}`}>Base mínima (UVT)</Label>
                            <Input
                                id={`base-minima-uvt-${parametrizacion.tipo_concepto}`}
                                type="number"
                                value={baseMinimaUvt}
                                onChange={(event) => setBaseMinimaUvt(event.target.value)}
                            />
                        </div>
                        <div>
                            <Label htmlFor={`tarifa-retefuente-${parametrizacion.tipo_concepto}`}>Tarifa (%)</Label>
                            <Input
                                id={`tarifa-retefuente-${parametrizacion.tipo_concepto}`}
                                type="number"
                                step={0.001}
                                value={tarifaRetefuente}
                                onChange={(event) => setTarifaRetefuente(event.target.value)}
                            />
                        </div>
                        <div>
                            <Label htmlFor={`cuenta-retefuente-${parametrizacion.tipo_concepto}`}>Cuenta por pagar</Label>
                            <Select options={cuentaOptions} placeholder="--" defaultValue={cuentaRetefuenteId} onChange={setCuentaRetefuenteId} />
                        </div>
                    </div>
                )}
            </div>

            <div className="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                <ToggleSwitchInput id={`aplica-reteica-${parametrizacion.tipo_concepto}`} label="Aplica ReteICA" checked={aplicaReteica} onChange={setAplicaReteica} />

                {aplicaReteica && (
                    <div className="mt-4 grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div className="md:col-span-2">
                            <p className="mb-2 text-xs text-gray-500 dark:text-gray-400">
                                La tarifa de ReteICA no se define aquí: sale de la tabla de tarifas por municipio y actividad económica de la empresa.
                            </p>
                            <Label htmlFor={`cuenta-reteica-${parametrizacion.tipo_concepto}`}>Cuenta por pagar</Label>
                            <Select options={cuentaOptions} placeholder="--" defaultValue={cuentaReteicaId} onChange={setCuentaReteicaId} />
                        </div>
                    </div>
                )}
            </div>

            <div className="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                <ToggleSwitchInput id={`aplica-reteiva-${parametrizacion.tipo_concepto}`} label="Aplica ReteIVA" checked={aplicaReteiva} onChange={setAplicaReteiva} />

                {aplicaReteiva && (
                    <div className="mt-4 grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div>
                            <Label htmlFor={`tarifa-reteiva-${parametrizacion.tipo_concepto}`}>Tarifa (%)</Label>
                            <Input
                                id={`tarifa-reteiva-${parametrizacion.tipo_concepto}`}
                                type="number"
                                step={0.001}
                                value={tarifaReteiva}
                                onChange={(event) => setTarifaReteiva(event.target.value)}
                            />
                        </div>
                        <div>
                            <Label htmlFor={`cuenta-reteiva-${parametrizacion.tipo_concepto}`}>Cuenta por pagar</Label>
                            <Select options={cuentaOptions} placeholder="--" defaultValue={cuentaReteivaId} onChange={setCuentaReteivaId} />
                        </div>
                    </div>
                )}
            </div>

            <div className="flex justify-end border-t border-gray-100 pt-6 dark:border-gray-800">
                <Button disabled={isLoading} startIcon={isLoading ? <Spinner size="sm" color="white" /> : undefined} onClick={() => void guardar()}>
                    {isLoading ? 'Guardando…' : 'Guardar'}
                </Button>
            </div>
        </div>
    );
};
