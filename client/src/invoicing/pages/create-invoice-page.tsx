import type { FC } from 'react';
import { useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router';

import { useResource } from '../../shared/hooks/use-resource';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import { Alert } from '@ui/alert/components/alert';
import { Button } from '@ui/button/components/button';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { DatePicker } from '@ui/forms/components/date-picker';
import { Label } from '@ui/forms/components/label';
import { Select } from '@ui/forms/components/select';
import { Spinner } from '@ui/spinner/components/spinner';
import { InvoiceLinesForm } from '../components/invoice-lines-form';
import type { InvoiceLineDraft } from '../props/invoice-lines-form-props';
import type { ApiInvoice } from '../types/api-invoice';

type CreditOption = { id: number; estado: string; cliente?: { tercero?: { nombre: string } } };

const LINEA_INICIAL: InvoiceLineDraft = { concepto: '', descripcion: '', valor: '' };

export const CreateInvoicePage: FC = () => {
    const navigate = useNavigate();
    const [searchParams] = useSearchParams();
    const { sendRequest, isLoading } = useSendRequest();

    const creditoInicial = searchParams.get('credito') ?? '';
    const [creditoId, setCreditoId] = useState(creditoInicial);
    const [fecha, setFecha] = useState(() => new Date().toISOString().slice(0, 10));
    const [fechaVencimiento, setFechaVencimiento] = useState('');
    const [lineas, setLineas] = useState<InvoiceLineDraft[]>([{ ...LINEA_INICIAL }]);
    const [error, setError] = useState<string | null>(null);

    const { data: creditosData, isLoading: cargandoCreditos } = useResource<{ data: CreditOption[] }>('/creditos?per_page=100');
    // Solo se factura un crédito desembolsado o pagado: el servidor lo exige igual.
    const creditos = (creditosData?.data ?? []).filter((credito) => ['desembolsado', 'pagado'].includes(credito.estado));

    const guardar = async () => {
        setError(null);

        try {
            const respuesta = await sendRequest<{ documento: ApiInvoice }>('/facturacion/documentos', {
                method: 'POST',
                body: {
                    credito_id: Number(creditoId),
                    fecha,
                    fecha_vencimiento: fechaVencimiento || null,
                    lineas: lineas.map((linea) => ({ concepto: linea.concepto, descripcion: linea.descripcion, valor: linea.valor })),
                },
            });

            navigate(`/facturacion/${respuesta.documento.id}`, { replace: true, state: { message: 'Factura creada en borrador. Revísala y emítela cuando esté lista.' } });
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        }
    };

    return (
        <div className="flex min-h-full flex-col gap-6 pb-6">
            <PageBreadcrumb pageTitle="Nueva factura" />

            {error && <Alert variant="danger" title="No se pudo crear la factura" message={error} onDismiss={() => setError(null)} />}

            <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                {cargandoCreditos ? (
                    <div className="flex justify-center py-6">
                        <Spinner size="lg" />
                    </div>
                ) : (
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                        <div>
                            <Label htmlFor="credito">Crédito desembolsado</Label>
                            <Select
                                options={creditos.map((credito) => ({
                                    value: String(credito.id),
                                    label: `#${credito.id} · ${credito.cliente?.tercero?.nombre ?? 'Sin cliente'}`,
                                }))}
                                placeholder="Seleccione un crédito..."
                                defaultValue={creditoInicial}
                                onChange={setCreditoId}
                            />
                        </div>
                        <div>
                            <Label htmlFor="fecha">Fecha</Label>
                            <DatePicker id="fecha" value={fecha} onChange={setFecha} />
                        </div>
                        <div>
                            <Label htmlFor="fecha-vencimiento">Fecha de vencimiento (opcional)</Label>
                            <DatePicker id="fecha-vencimiento" value={fechaVencimiento} onChange={setFechaVencimiento} />
                        </div>
                    </div>
                )}
            </div>

            <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                <InvoiceLinesForm lineas={lineas} onChange={setLineas} />
            </div>

            <div className="flex justify-end gap-3">
                <Button variant="outline" onClick={() => navigate('/facturacion')}>
                    Cancelar
                </Button>
                <Button disabled={isLoading || !creditoId} onClick={() => void guardar()}>
                    {isLoading ? 'Guardando…' : 'Guardar borrador'}
                </Button>
            </div>
        </div>
    );
};
