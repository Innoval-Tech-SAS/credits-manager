import type { FC, SubmitEvent } from 'react';
import { useState } from 'react';
import { useNavigate } from 'react-router';

import { useSendRequest } from '../../shared/hooks/use-send-request';
import { AdvancePendingNotice } from '../../advances/components/advance-pending-notice';
import { MunicipalitySelector } from '../../geography/components/municipality-selector';
import { ThirdPartySelector } from '../../third-parties/components/third-party-selector';
import type { ApiThirdParty } from '../../third-parties/types/api-third-party';
import { useResource } from '../../shared/hooks/use-resource';
import { Button } from '@ui/button/components/button';
import { DatePicker } from '@ui/forms/components/date-picker';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { Select } from '@ui/forms/components/select';
import { Spinner } from '@ui/spinner/components/spinner';
import { Save } from 'lucide-react';
import type { ConceptAccrualFormProps } from '../props/concept-accrual-form-props';
import type { ApiVoucher } from '../types/api-voucher';
import type { ApiCostCenter } from '../types/api-cost-center';

export const ConceptAccrualForm: FC<ConceptAccrualFormProps> = ({ tiposGasto }) => {
    const navigate = useNavigate();
    const { sendRequest } = useSendRequest();

    const tipoGastoOptions = tiposGasto.map((tipo) => ({ value: String(tipo.id), label: `${tipo.codigo} - ${tipo.nombre}` }));

    const [tipoGastoId, setTipoGastoId] = useState('');
    const [tercero, setTercero] = useState<ApiThirdParty | null>(null);
    const [municipioId, setMunicipioId] = useState('');
    const [centroCostoId, setCentroCostoId] = useState('');
    const [fecha, setFecha] = useState(() => new Date().toISOString().slice(0, 10));
    const [fechaVencimiento, setFechaVencimiento] = useState('');
    const [numeroDocumento, setNumeroDocumento] = useState('');
    const [concepto, setConcepto] = useState('');
    const [valorBruto, setValorBruto] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const { data: centrosRespuesta } = useResource<{ data: ApiCostCenter[] }>('/contabilidad/centros-costo?per_page=100');
    const centrosActivos = (centrosRespuesta?.data ?? []).filter((centro) => centro.activo);
    const centroOptions = centrosActivos.map((centro) => ({ value: String(centro.id), label: `${centro.codigo} - ${centro.nombre}` }));

    const tipoGastoSeleccionado = tiposGasto.find((tipo) => String(tipo.id) === tipoGastoId);
    const requiereTercero = tipoGastoSeleccionado?.requiere_tercero ?? false;

    const handleSubmit = async (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();
        setError(null);
        setIsSubmitting(true);

        try {
            const respuesta = await sendRequest<{ comprobante: ApiVoucher }>('/contabilidad/comprobantes/causar-concepto', {
                method: 'POST',
                body: {
                    tipo_gasto_id: Number(tipoGastoId),
                    tercero_id: tercero?.id ?? null,
                    municipio_id: municipioId ? Number(municipioId) : null,
                    centro_costo_id: centroCostoId ? Number(centroCostoId) : null,
                    fecha,
                    fecha_vencimiento: fechaVencimiento || null,
                    numero_documento: numeroDocumento,
                    concepto: concepto || null,
                    valor_bruto: parseFloat(valorBruto) || 0,
                },
            });

            navigate(`/contabilidad/comprobantes/${respuesta.comprobante.id}`, {
                replace: true,
                state: { message: 'Comprobante creado en borrador. Otro usuario (o tú mismo) debe revisarlo y contabilizarlo.' },
            });
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
                        <Label htmlFor="tipo-gasto">Concepto</Label>
                        <Select id="tipo-gasto" options={tipoGastoOptions} defaultValue={tipoGastoId} onChange={setTipoGastoId} />
                    </div>

                    <div>
                        <Label htmlFor="tercero">Tercero{requiereTercero ? '' : ' (opcional)'}</Label>
                        <ThirdPartySelector id="tercero" selected={tercero} onSelect={setTercero} />
                    </div>

                    <div>
                        <Label htmlFor="fecha">Fecha</Label>
                        <DatePicker id="fecha" name="fecha" required value={fecha} onChange={setFecha} />
                    </div>
                </div>

                <AdvancePendingNotice tercero={tercero} />

                <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div>
                        <Label htmlFor="centro-costo">Centro de costo (opcional)</Label>
                        {centroOptions.length > 0 ? (
                            <Select id="centro-costo" options={centroOptions} defaultValue={centroCostoId} onChange={setCentroCostoId} />
                        ) : (
                            <p className="text-sm text-gray-500 dark:text-gray-400">No hay centros de costo activos.</p>
                        )}
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div>
                        <Label htmlFor="numero-documento">Número de documento</Label>
                        <Input
                            id="numero-documento"
                            name="numero-documento"
                            required
                            value={numeroDocumento}
                            onChange={(event) => setNumeroDocumento(event.target.value)}
                        />
                    </div>

                    <div className="md:col-span-2">
                        <Label htmlFor="concepto">Concepto (opcional)</Label>
                        <Input id="concepto" name="concepto" value={concepto} onChange={(event) => setConcepto(event.target.value)} />
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div>
                        <Label htmlFor="fecha-vencimiento">Fecha de vencimiento (opcional)</Label>
                        <DatePicker id="fecha-vencimiento" name="fecha-vencimiento" value={fechaVencimiento} onChange={setFechaVencimiento} />
                    </div>
                </div>

                <div className="md:w-1/3">
                    <Label htmlFor="valor-bruto">Valor bruto</Label>
                    <Input
                        id="valor-bruto"
                        name="valor-bruto"
                        type="number"
                        step={0.01}
                        required
                        value={valorBruto}
                        onChange={(event) => setValorBruto(event.target.value)}
                    />
                </div>

                <div>
                    <p className="mb-3 text-sm font-medium text-gray-700 dark:text-gray-400">Municipio del documento (opcional, define la tarifa de ReteICA)</p>
                    <MunicipalitySelector onChange={setMunicipioId} />
                </div>

                <p className="text-xs text-gray-500 dark:text-gray-400">
                    El IVA y las retenciones (ReteFuente, ReteICA, ReteIVA) se calculan automáticamente según la parametrización vigente
                    del concepto y los datos fiscales del tercero — no se digitan aquí.
                </p>

                <div className="flex justify-end border-t border-gray-100 pt-6 dark:border-gray-800">
                    <Button
                        type="submit"
                        disabled={isSubmitting || !tipoGastoId || !numeroDocumento || (requiereTercero && !tercero)}
                        startIcon={isSubmitting ? <Spinner size="sm" color="white" /> : undefined}
                        endIcon={isSubmitting ? undefined : <Save className="size-4" />}
                    >
                        {isSubmitting ? 'Causando…' : 'Causar concepto'}
                    </Button>
                </div>
            </div>
        </form>
    );
};
