import type { FC } from 'react';
import { useState } from 'react';
import { useNavigate } from 'react-router';
import { IdCard, Send } from 'lucide-react';

import { useResource } from '../../shared/hooks/use-resource';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import { ThirdPartySelector } from '../../third-parties/components/third-party-selector';
import type { ApiThirdParty } from '../../third-parties/types/api-third-party';
import type { ApiAccount } from '../../accounting/types/api-account';
import type { ApiVoucherType } from '../../accounting/types/api-voucher-type';
import type { PaginatedResponse } from '../../shared/types/paginated-response';
import { Alert } from '@ui/alert/components/alert';
import { Button } from '@ui/button/components/button';
import { DatePicker } from '@ui/forms/components/date-picker';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { Select } from '@ui/forms/components/select';
import { Spinner } from '@ui/spinner/components/spinner';
import type { ApiAdvance } from '../types/api-advance';

type Lado = 'proveedor' | 'cliente';

const LADO_OPTIONS = [
    { value: 'proveedor', label: 'Anticipo a proveedor' },
    { value: 'cliente', label: 'Anticipo de cliente' },
];

/** Dinero que se mueve antes de que exista la factura: `POST /anticipos/proveedores` o `/anticipos/clientes`. */
export const CreateAdvanceForm: FC = () => {
    const navigate = useNavigate();
    const { sendRequest, isLoading: isSubmitting } = useSendRequest();

    const { data: cuentasData } = useResource<{ cuentas: ApiAccount[] }>('/contabilidad/plan-cuentas');
    const { data: tiposData } = useResource<PaginatedResponse<ApiVoucherType>>('/contabilidad/tipos-comprobante?per_page=100');
    const cuentas = (cuentasData?.cuentas ?? []).filter((cuenta) => cuenta.permite_movimiento && cuenta.activa);
    const tipos = tiposData?.data ?? [];

    const [lado, setLado] = useState<Lado>('proveedor');
    const [tercero, setTercero] = useState<ApiThirdParty | null>(null);
    const [fecha, setFecha] = useState(() => new Date().toISOString().slice(0, 10));
    const [numeroDocumento, setNumeroDocumento] = useState('');
    const [concepto, setConcepto] = useState('');
    const [valor, setValor] = useState('');
    const [cuentaAnticipoId, setCuentaAnticipoId] = useState('');
    const [cuentaCajaId, setCuentaCajaId] = useState('');
    const [tipoComprobanteId, setTipoComprobanteId] = useState('');
    const [error, setError] = useState<string | null>(null);

    const formularioValido =
        tercero !== null &&
        fecha !== '' &&
        numeroDocumento.trim() !== '' &&
        concepto.trim() !== '' &&
        Number(valor) > 0 &&
        cuentaAnticipoId !== '' &&
        cuentaCajaId !== '' &&
        tipoComprobanteId !== '';

    const registrar = async () => {
        if (!tercero) {
            return;
        }

        setError(null);

        try {
            const ruta = lado === 'proveedor' ? '/anticipos/proveedores' : '/anticipos/clientes';
            const respuesta = await sendRequest<{ anticipo: ApiAdvance }>(ruta, {
                method: 'POST',
                body: {
                    tercero_id: tercero.id,
                    fecha,
                    numero_documento: numeroDocumento.trim(),
                    concepto: concepto.trim(),
                    valor,
                    cuenta_anticipo_id: Number(cuentaAnticipoId),
                    cuenta_caja_id: Number(cuentaCajaId),
                    tipo_comprobante_id: Number(tipoComprobanteId),
                },
            });

            navigate('/contabilidad/anticipos/cruzar', {
                replace: true,
                state: { message: `Anticipo ${respuesta.anticipo.numero_documento} registrado por ${respuesta.anticipo.saldo}.` },
            });
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        }
    };

    return (
        <div className="flex flex-col gap-6">
            {error && <Alert variant="danger" title="No se pudo registrar el anticipo" message={error} onDismiss={() => setError(null)} />}

            <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div>
                        <Label htmlFor="lado">Tipo de anticipo</Label>
                        <Select id="lado" options={LADO_OPTIONS} defaultValue={lado} onChange={(valor) => setLado(valor as Lado)} />
                    </div>

                    <div className="md:col-span-2">
                        <Label htmlFor="tercero">Tercero</Label>
                        <ThirdPartySelector id="tercero" selected={tercero} onSelect={setTercero} />
                    </div>
                </div>

                <div className="mt-6 grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div>
                        <Label htmlFor="fecha">Fecha</Label>
                        <DatePicker id="fecha" value={fecha} onChange={setFecha} required />
                    </div>

                    <div>
                        <Label htmlFor="numero-documento">Documento soporte (ej. recibo RC-015)</Label>
                        <Input
                            id="numero-documento"
                            startIcon={<IdCard className="size-4" />}
                            value={numeroDocumento}
                            maxLength={60}
                            onChange={(event) => setNumeroDocumento(event.target.value)}
                        />
                    </div>

                    <div>
                        <Label htmlFor="valor">Valor</Label>
                        <Input id="valor" type="number" min="0.01" step={0.01} value={valor} onChange={(event) => setValor(event.target.value)} />
                    </div>
                </div>

                <div className="mt-6">
                    <Label htmlFor="concepto">Concepto</Label>
                    <Input id="concepto" value={concepto} maxLength={255} onChange={(event) => setConcepto(event.target.value)} />
                </div>

                <div className="mt-6 grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div>
                        <Label htmlFor="cuenta-anticipo">
                            {lado === 'proveedor' ? 'Cuenta de anticipos a proveedores' : 'Cuenta de anticipos de clientes'}
                        </Label>
                        <Select
                            id="cuenta-anticipo"
                            options={cuentas.map((cuenta) => ({ value: String(cuenta.id), label: `${cuenta.codigo} - ${cuenta.nombre}` }))}
                            placeholder="Selecciona la cuenta"
                            onChange={setCuentaAnticipoId}
                        />
                    </div>

                    <div>
                        <Label htmlFor="cuenta-caja">Cuenta de caja o banco</Label>
                        <Select
                            id="cuenta-caja"
                            options={cuentas.map((cuenta) => ({ value: String(cuenta.id), label: `${cuenta.codigo} - ${cuenta.nombre}` }))}
                            placeholder="Selecciona la cuenta"
                            onChange={setCuentaCajaId}
                        />
                    </div>

                    <div>
                        <Label htmlFor="tipo-comprobante">Tipo de comprobante</Label>
                        <Select
                            id="tipo-comprobante"
                            options={tipos.map((tipo) => ({ value: String(tipo.id), label: `${tipo.codigo} - ${tipo.nombre}` }))}
                            placeholder="Selecciona el tipo"
                            onChange={setTipoComprobanteId}
                        />
                    </div>
                </div>

                <p className="mt-6 text-xs text-gray-500 dark:text-gray-400">
                    {lado === 'proveedor'
                        ? 'Se debita la cuenta de anticipos y se acredita la cuenta de caja o banco.'
                        : 'Se debita la cuenta de caja o banco y se acredita la cuenta de anticipos de clientes.'}
                </p>
            </div>

            <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Button variant="outline" onClick={() => navigate(-1)} disabled={isSubmitting}>
                    Cancelar
                </Button>
                <Button
                    disabled={!formularioValido || isSubmitting}
                    startIcon={isSubmitting ? <Spinner size="sm" color="white" /> : <Send className="size-4" />}
                    onClick={() => void registrar()}
                >
                    {isSubmitting ? 'Registrando…' : 'Registrar anticipo'}
                </Button>
            </div>
        </div>
    );
};
