import type { FC } from 'react';
import { useState } from 'react';

import { usePermission } from '../../auth/hooks/use-permission';
import { useResource } from '../../shared/hooks/use-resource';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import { Alert } from '@ui/alert/components/alert';
import { Button } from '@ui/button/components/button';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { Spinner } from '@ui/spinner/components/spinner';
import type { ApiInvoiceSequence } from '../types/api-invoice';

type Borrador = { prefijo: string; numeroInicial: string };

/** Prefijo y número inicial de cada secuencia de facturación. Los números ya emitidos conservan su prefijo. */
export const InvoiceSequencesPage: FC = () => {
    const puedeConfigurar = usePermission('facturacion', 'configurar');
    const { sendRequest, isLoading: guardando } = useSendRequest();
    const { data, error, refetch } = useResource<{ consecutivos: ApiInvoiceSequence[] }>('/facturacion/consecutivos');

    const [borradores, setBorradores] = useState<Record<string, Borrador>>({});
    const [mensaje, setMensaje] = useState<string | null>(null);
    const [errorAccion, setErrorAccion] = useState<string | null>(null);

    if (error) {
        return <Alert variant="danger" title="No se pudieron cargar los consecutivos" message={error} />;
    }

    if (!data) {
        return (
            <div className="flex justify-center py-12">
                <Spinner size="lg" />
            </div>
        );
    }

    const borradorDe = (secuencia: ApiInvoiceSequence): Borrador =>
        borradores[secuencia.tipo] ?? { prefijo: secuencia.prefijo ?? '', numeroInicial: '' };

    const cambiar = (secuencia: ApiInvoiceSequence, campo: keyof Borrador, valor: string) => {
        setBorradores({ ...borradores, [secuencia.tipo]: { ...borradorDe(secuencia), [campo]: valor } });
    };

    const guardar = async (secuencia: ApiInvoiceSequence) => {
        const borrador = borradorDe(secuencia);
        const cuerpo: { prefijo: string; numero_inicial?: number } = { prefijo: borrador.prefijo.trim() };

        if (secuencia.numero_inicial_editable && borrador.numeroInicial.trim() !== '') {
            cuerpo.numero_inicial = Number(borrador.numeroInicial);
        }

        try {
            await sendRequest(`/facturacion/consecutivos/${secuencia.tipo}`, { method: 'PATCH', body: cuerpo });
            const resto = { ...borradores };
            delete resto[secuencia.tipo];
            setBorradores(resto);
            setErrorAccion(null);
            setMensaje(`Consecutivo de ${secuencia.etiqueta} guardado.`);
            void refetch();
        } catch (caught) {
            setMensaje(null);
            setErrorAccion(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        }
    };

    return (
        <div className="flex min-h-full flex-col gap-6 pb-6">
            <PageBreadcrumb pageTitle="Consecutivos de facturación" />

            {mensaje && <Alert variant="success" title="Listo" message={mensaje} onDismiss={() => setMensaje(null)} />}
            {errorAccion && <Alert variant="danger" title="No se pudo guardar" message={errorAccion} onDismiss={() => setErrorAccion(null)} />}

            <div className="flex flex-col gap-4">
                {data.consecutivos.map((secuencia) => {
                    const borrador = borradorDe(secuencia);
                    const cambiado = borradores[secuencia.tipo] !== undefined;

                    return (
                        <div key={secuencia.tipo} className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                            <div className="grid grid-cols-1 gap-6 md:grid-cols-[1fr_1fr_1fr_auto] md:items-end">
                                <div>
                                    <p className="text-sm font-medium text-gray-800 dark:text-white/90">{secuencia.etiqueta}</p>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">Siguiente número: {secuencia.siguiente}</p>
                                </div>
                                <div>
                                    <Label htmlFor={`prefijo-${secuencia.tipo}`}>Prefijo</Label>
                                    <Input
                                        id={`prefijo-${secuencia.tipo}`}
                                        value={borrador.prefijo}
                                        maxLength={10}
                                        disabled={!puedeConfigurar}
                                        onChange={(event) => cambiar(secuencia, 'prefijo', event.target.value)}
                                    />
                                </div>
                                <div>
                                    <Label htmlFor={`inicial-${secuencia.tipo}`}>Número inicial</Label>
                                    <Input
                                        id={`inicial-${secuencia.tipo}`}
                                        type="number"
                                        min="1"
                                        placeholder={secuencia.numero_inicial_editable ? String(secuencia.siguiente) : 'Ya hay documentos emitidos'}
                                        value={borrador.numeroInicial}
                                        disabled={!puedeConfigurar || !secuencia.numero_inicial_editable}
                                        onChange={(event) => cambiar(secuencia, 'numeroInicial', event.target.value)}
                                    />
                                </div>
                                {puedeConfigurar && (
                                    <Button disabled={!cambiado || guardando} onClick={() => void guardar(secuencia)}>
                                        Guardar
                                    </Button>
                                )}
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
};
