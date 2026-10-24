import type { FC } from 'react';
import { useState } from 'react';
import { useNavigate } from 'react-router';

import { useResource } from '../../shared/hooks/use-resource';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import { formatMoney } from '../../shared/utils/format-money';
import type { PaginatedResponse } from '../../shared/types/paginated-response';
import { ClientFullSelect } from '../../clients/components/client-full-select';
import type { ApiClient } from '../../clients/types/api-client';
import type { ApiCredit } from '../../credits/types/api-credit';
import { Alert } from '@ui/alert/components/alert';
import { Button } from '@ui/button/components/button';
import { Checkbox } from '@ui/forms/components/checkbox';
import { Label } from '@ui/forms/components/label';
import { Select } from '@ui/forms/components/select';
import { FieldSkeleton } from '@ui/skeleton/components/field-skeleton';
import { SkeletonStatus } from '@ui/skeleton/components/skeleton-status';
import { ThirdPartyFiscalSummary } from './third-party-fiscal-summary';
import type { ApiInvoice, ApiSuggestedConcept } from '../types/api-invoice';

/** Factura, de una sola vez, el interés corriente de una o más cuotas ya pagadas (ver `conceptosSugeridos()` /
 * `POST .../intereses-pagados`): queda emitida y cobrada en el acto, sin tocar banco otra vez. */
export const InvoicePaidInterestForm: FC = () => {
    const navigate = useNavigate();
    const { sendRequest, isLoading } = useSendRequest();

    const [cliente, setCliente] = useState<ApiClient | null>(null);
    const [creditoId, setCreditoId] = useState('');
    const [seleccionadas, setSeleccionadas] = useState<number[]>([]);
    const [error, setError] = useState<string | null>(null);

    const creditosParams = cliente ? new URLSearchParams({ cliente_id: String(cliente.id), per_page: '100' }).toString() : '';
    const { data: creditosData, isLoading: cargandoCreditos } = useResource<PaginatedResponse<ApiCredit>>(cliente ? `/creditos?${creditosParams}` : null);
    const creditos = (creditosData?.data ?? []).filter((credito) => ['desembolsado', 'pagado'].includes(credito.estado));

    const { data: sugeridosData, isLoading: cargandoSugeridos } = useResource<{ conceptos: ApiSuggestedConcept[] }>(
        creditoId ? `/facturacion/documentos/conceptos-sugeridos?credito_id=${creditoId}` : null,
    );
    const sugeridos = sugeridosData?.conceptos ?? [];

    const seleccionarCliente = (seleccionado: ApiClient | null) => {
        setCliente(seleccionado);
        setCreditoId('');
        setSeleccionadas([]);
    };

    const seleccionarCredito = (valor: string) => {
        setCreditoId(valor);
        setSeleccionadas([]);
    };

    const alternar = (cuotaId: number, marcada: boolean) => {
        setSeleccionadas((actual) => (marcada ? [...actual, cuotaId] : actual.filter((id) => id !== cuotaId)));
    };

    const total = sugeridos.filter((s) => seleccionadas.includes(s.cuota_id)).reduce((acumulado, s) => acumulado + parseFloat(s.valor), 0);

    const facturar = async () => {
        setError(null);

        try {
            const respuesta = await sendRequest<{ documento: ApiInvoice }>('/facturacion/documentos/intereses-pagados', {
                method: 'POST',
                body: { credito_id: Number(creditoId), cuota_ids: seleccionadas },
            });

            navigate(`/facturacion/${respuesta.documento.id}`, { replace: true, state: { message: 'Factura de intereses emitida y cobrada.' } });
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        }
    };

    const puedeFacturar = creditoId !== '' && seleccionadas.length > 0;

    return (
        <div className="flex flex-col gap-6">
            {error && <Alert variant="danger" title="No se pudo facturar" message={error} onDismiss={() => setError(null)} />}

            <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                <h3 className="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">1. Cliente</h3>
                <Label htmlFor="cliente">Cliente</Label>
                <ClientFullSelect id="cliente" selected={cliente} onSelect={seleccionarCliente} />
            </div>

            {cliente && (
                <>
                    <ThirdPartyFiscalSummary tercero={cliente.tercero} />

                    <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                        <h3 className="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">2. Crédito</h3>
                        {cargandoCreditos ? (
                            <SkeletonStatus className="grid grid-cols-1 gap-6 md:grid-cols-3">
                                <FieldSkeleton />
                            </SkeletonStatus>
                        ) : (
                            <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                                <div>
                                    <Label htmlFor="credito">Crédito</Label>
                                    <Select
                                        id="credito"
                                        key={cliente.id}
                                        options={creditos.map((c) => ({ value: String(c.id), label: `#${c.id} · Saldo ${formatMoney(c.saldo ?? '0')}` }))}
                                        placeholder={creditos.length === 0 ? 'Este cliente no tiene créditos disponibles' : 'Seleccione un crédito...'}
                                        defaultValue={creditoId}
                                        onChange={seleccionarCredito}
                                    />
                                </div>
                            </div>
                        )}
                    </div>
                </>
            )}

            {creditoId !== '' && (
                <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                    <h3 className="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">3. Cuotas pagadas sin facturar</h3>

                    {cargandoSugeridos ? (
                        <SkeletonStatus>
                            <FieldSkeleton />
                        </SkeletonStatus>
                    ) : sugeridos.length === 0 ? (
                        <p className="text-sm text-gray-500 dark:text-gray-400">Este crédito no tiene interés de cuotas pagadas pendiente de facturar.</p>
                    ) : (
                        <div className="flex flex-col gap-3">
                            {sugeridos.map((sugerido) => (
                                <div
                                    key={sugerido.cuota_id}
                                    className="flex items-center justify-between gap-3 rounded-lg border border-gray-200 p-3 dark:border-gray-800"
                                >
                                    <Checkbox
                                        label={sugerido.concepto}
                                        checked={seleccionadas.includes(sugerido.cuota_id)}
                                        onChange={(marcada) => alternar(sugerido.cuota_id, marcada)}
                                    />
                                    <span className="text-sm font-medium text-gray-800 dark:text-white/90">{formatMoney(sugerido.valor)}</span>
                                </div>
                            ))}

                            <div className="mt-2 flex items-center justify-end gap-3 border-t border-gray-200 pt-4 dark:border-gray-800">
                                <span className="text-sm text-gray-500 dark:text-gray-400">Total a facturar</span>
                                <span className="text-base font-semibold text-gray-800 dark:text-white/90">{formatMoney(total)}</span>
                            </div>
                        </div>
                    )}
                </div>
            )}

            <div className="flex justify-end gap-3">
                <Button variant="outline" onClick={() => navigate('/facturacion')}>
                    Cancelar
                </Button>
                <Button disabled={isLoading || !puedeFacturar} onClick={() => void facturar()}>
                    {isLoading ? 'Facturando…' : 'Facturar y cobrar'}
                </Button>
            </div>
        </div>
    );
};
