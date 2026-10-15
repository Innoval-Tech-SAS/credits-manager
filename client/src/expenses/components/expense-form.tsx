import type { FC, SubmitEvent } from 'react';
import { useState } from 'react';
import { useNavigate } from 'react-router';

import { useSendRequest } from '../../shared/hooks/use-send-request';
import { ACCEPTED_DOCUMENT_TYPES } from '../../shared/constants/accepted-document-types';
import { AdvancePendingNotice } from '../../advances/components/advance-pending-notice';
import { MunicipalitySelector } from '../../geography/components/municipality-selector';
import { ThirdPartySelector } from '../../third-parties/components/third-party-selector';
import type { ApiThirdParty } from '../../third-parties/types/api-third-party';
import { Button } from '@ui/button/components/button';
import { DatePicker } from '@ui/forms/components/date-picker';
import { Dropzone } from '@ui/dropzone/components/dropzone';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { Select } from '@ui/forms/components/select';
import { Spinner } from '@ui/spinner/components/spinner';
import { Save } from 'lucide-react';
import type { ExpenseFormProps } from '../props/expense-form-props';
import type { ApiExpense } from '../types/api-expense';
import type { ApiCostCenter } from '../../accounting/types/api-cost-center';
import { useResource } from '../../shared/hooks/use-resource';

export const ExpenseForm: FC<ExpenseFormProps> = ({ tiposGasto }) => {
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
    const [soporte, setSoporte] = useState<File | null>(null);
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
            const formData = new FormData();
            formData.append('tipo_gasto_id', tipoGastoId);
            if (tercero) {
                formData.append('tercero_id', String(tercero.id));
            }
            if (municipioId) {
                formData.append('municipio_id', municipioId);
            }
            if (centroCostoId) {
                formData.append('centro_costo_id', centroCostoId);
            }
            formData.append('fecha', fecha);
            if (fechaVencimiento) {
                formData.append('fecha_vencimiento', fechaVencimiento);
            }
            formData.append('numero_documento', numeroDocumento);
            formData.append('concepto', concepto);
            formData.append('valor_bruto', String(parseFloat(valorBruto) || 0));
            if (soporte) {
                formData.append('soporte', soporte);
            }

            const respuesta = await sendRequest<{ gasto: ApiExpense }>('/gastos', { method: 'POST', body: formData });

            navigate(`/gastos/${respuesta.gasto.id}`, { replace: true, state: { message: 'Gasto causado y contabilizado correctamente.' } });
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
                        <Label htmlFor="centro-costo">Centro de costo</Label>
                        {centroOptions.length > 0 ? (
                            <Select id="centro-costo" options={centroOptions} defaultValue={centroCostoId} onChange={setCentroCostoId} />
                        ) : (
                            <p className="text-sm text-gray-500 dark:text-gray-400">No hay centros de costo activos. Créalos en Contabilidad antes de registrar gastos.</p>
                        )}
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div>
                        <Label htmlFor="numero-documento">Documento soporte</Label>
                        <Input
                            id="numero-documento"
                            name="numero-documento"
                            required
                            value={numeroDocumento}
                            onChange={(event) => setNumeroDocumento(event.target.value)}
                        />
                    </div>

                    <div className="md:col-span-2">
                        <Label htmlFor="concepto">Concepto</Label>
                        <Input id="concepto" name="concepto" required value={concepto} onChange={(event) => setConcepto(event.target.value)} />
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

                <div>
                    <Label htmlFor="soporte">Soporte (factura o documento del proveedor)</Label>
                    {soporte ? (
                        <div className="flex items-center justify-between rounded-xl border border-gray-200 px-4 py-3 text-sm dark:border-gray-800">
                            <span className="text-gray-700 dark:text-gray-300">{soporte.name}</span>
                            <button type="button" className="text-error-600 hover:underline dark:text-error-400" onClick={() => setSoporte(null)}>
                                Quitar
                            </button>
                        </div>
                    ) : (
                        <Dropzone id="soporte" accept={ACCEPTED_DOCUMENT_TYPES} hint="PDF, PNG, JPG o WEBP" onFileAccepted={setSoporte} />
                    )}
                </div>

                <p className="text-xs text-gray-500 dark:text-gray-400">
                    El IVA y las retenciones (ReteFuente, ReteICA, ReteIVA) se calculan automáticamente según la parametrización vigente
                    del tipo de gasto y los datos fiscales del tercero — no se digitan aquí.
                </p>

                <div className="flex justify-end border-t border-gray-100 pt-6 dark:border-gray-800">
                    <Button
                        type="submit"
                        disabled={isSubmitting || !tipoGastoId || !soporte || (requiereTercero && !tercero)}
                        startIcon={isSubmitting ? <Spinner size="sm" color="white" /> : undefined}
                        endIcon={isSubmitting ? undefined : <Save className="size-4" />}
                    >
                        {isSubmitting ? 'Causando…' : 'Causar gasto'}
                    </Button>
                </div>
            </div>
        </form>
    );
};
