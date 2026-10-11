import type { FC } from 'react';
import { useState } from 'react';
import { useParams } from 'react-router';
import { Lock, Unlock } from 'lucide-react';

import { useResource } from '../../shared/hooks/use-resource';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import { usePermission } from '../../auth/hooks/use-permission';
import { formatMoney } from '../../shared/utils/format-money';
import { Alert } from '@ui/alert/components/alert';
import { Button } from '@ui/button/components/button';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { DataTable } from '@ui/table/components/data-table';
import { MotivoModal } from '../../shared/components/motivo-modal';
import { CloseStatementModal } from '../components/close-statement-modal';
import { SendToBridgeModal } from '../components/send-to-bridge-modal';
import {
    MOVEMENT_MATCH_STATUS_BADGE_CLASSES,
    MOVEMENT_MATCH_STATUS_LABELS,
    SUGGESTION_STATUS_BADGE_CLASSES,
    SUGGESTION_STATUS_LABELS,
} from '../constants/match-status';
import type { ApiAsientoConciliable, ApiCandidatosConciliacion, ApiExtractoMovimiento, ApiMarcaConciliacion } from '../types/api-match-conciliacion';

/** Matching entre los movimientos pendientes de un extracto y los asientos de la cuenta PUC del banco. */
export const ReconciliationMatchingPage: FC = () => {
    const { id } = useParams<{ id: string }>();
    const extractoId = Number(id);

    const canConciliar = usePermission('conciliacion', 'conciliar');
    const canCerrar = usePermission('conciliacion', 'cerrar');
    const canReabrir = usePermission('conciliacion', 'reabrir');

    const [seleccionado, setSeleccionado] = useState<number | null>(null);
    const [puenteMovimiento, setPuenteMovimiento] = useState<ApiExtractoMovimiento | null>(null);
    const [marcaADesmarcar, setMarcaADesmarcar] = useState<number | null>(null);
    const [isCerrarOpen, setIsCerrarOpen] = useState(false);
    const [isReabrirOpen, setIsReabrirOpen] = useState(false);
    const [successMessage, setSuccessMessage] = useState<string | null>(null);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    const { data, isLoading, refetch } = useResource<ApiCandidatosConciliacion>(`/conciliacion/extractos/${extractoId}/candidatos`);
    const { sendRequest, isLoading: isEnviando } = useSendRequest();

    const movimientos = data?.movimientos ?? [];
    const asientos = data?.asientos ?? [];
    const sugerenciaDelSeleccionado = data?.sugerencias.find((sugerencia) => sugerencia.movimiento_extracto_id === seleccionado) ?? null;
    const pendientes = movimientos.filter((movimiento) => movimiento.estado === 'pendiente').length;

    const onExito = (mensaje: string) => {
        setSeleccionado(null);
        setPuenteMovimiento(null);
        setMarcaADesmarcar(null);
        setIsCerrarOpen(false);
        setIsReabrirOpen(false);
        setSuccessMessage(mensaje);
        refetch();
    };

    const onFallo = (mensaje: string) => {
        setErrorMessage(mensaje);
    };

    const conciliar = async (asiento: ApiAsientoConciliable) => {
        if (seleccionado === null) {
            return;
        }

        try {
            await sendRequest(`/conciliacion/movimientos/${seleccionado}/conciliar`, {
                method: 'POST',
                body: { asiento_contable_id: asiento.id },
            });
            onExito(`Movimiento #${seleccionado} conciliado con el asiento #${asiento.id}.`);
        } catch (caught) {
            onFallo(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        }
    };

    const desmarcar = async (motivo: string) => {
        if (marcaADesmarcar === null) {
            return;
        }

        try {
            await sendRequest<{ marca: ApiMarcaConciliacion }>(`/conciliacion/marcas/${marcaADesmarcar}/desmarcar`, {
                method: 'POST',
                body: { motivo },
            });
            onExito('Marca desmarcada: el movimiento vuelve a estar pendiente.');
        } catch (caught) {
            onFallo(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        }
    };

    const reabrir = async (motivo: string) => {
        try {
            await sendRequest(`/conciliacion/extractos/${extractoId}/reabrir`, { method: 'POST', body: { motivo } });
            onExito(`Extracto #${extractoId} reabierto.`);
        } catch (caught) {
            onFallo(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        }
    };

    return (
        <div className="flex min-h-full flex-col gap-6 pb-6">
            <PageBreadcrumb pageTitle="Conciliar extracto" />

            {successMessage && <Alert variant="success" title="Listo" message={successMessage} onDismiss={() => setSuccessMessage(null)} />}
            {errorMessage && <Alert variant="danger" title="Error" message={errorMessage} onDismiss={() => setErrorMessage(null)} />}

            {data && (canCerrar || canReabrir) && (data.extracto.estado === 'cargado' || data.extracto.estado === 'cerrado') && (
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
                    {canReabrir && data.extracto.estado === 'cerrado' && (
                        <Button variant="outline" startIcon={<Unlock className="size-4" />} onClick={() => setIsReabrirOpen(true)}>
                            Reabrir extracto
                        </Button>
                    )}
                    {canCerrar && data.extracto.estado === 'cargado' && (
                        <Button startIcon={<Lock className="size-4" />} onClick={() => setIsCerrarOpen(true)}>
                            Cerrar extracto
                        </Button>
                    )}
                </div>
            )}

            {data && (
                <Alert
                    variant={pendientes === 0 ? 'success' : 'info'}
                    title={pendientes === 0 ? 'Extracto sin pendientes' : `${pendientes} movimiento(s) pendiente(s)`}
                    message={
                        pendientes === 0
                            ? 'Todos los movimientos de este extracto ya tienen una marca de conciliación o puente.'
                            : 'Selecciona un movimiento del extracto y luego un asiento del libro con el mismo valor para conciliarlos.'
                    }
                />
            )}

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                    <h2 className="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">Extracto</h2>

                    <DataTable
                        columns={[
                            { key: 'fecha', label: 'Fecha' },
                            { key: 'descripcion', label: 'Descripción' },
                            { key: 'valor', label: 'Valor', render: (row) => formatMoney(row.valor) },
                            {
                                key: 'estado',
                                label: 'Estado',
                                render: (row) => {
                                    const sugerencia = data?.sugerencias.find((item) => item.movimiento_extracto_id === row.id);

                                    return (
                                        <div className="flex flex-col gap-1">
                                            <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${MOVEMENT_MATCH_STATUS_BADGE_CLASSES[row.estado]}`}>
                                                {MOVEMENT_MATCH_STATUS_LABELS[row.estado]}
                                            </span>
                                            {row.estado === 'pendiente' && sugerencia && (
                                                <span
                                                    className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${SUGGESTION_STATUS_BADGE_CLASSES[sugerencia.estado_sugerido]}`}
                                                >
                                                    {SUGGESTION_STATUS_LABELS[sugerencia.estado_sugerido]}
                                                </span>
                                            )}
                                        </div>
                                    );
                                },
                            },
                            {
                                key: 'acciones',
                                label: 'Acción',
                                render: (row) =>
                                    canConciliar && (
                                        <AccionesMovimiento
                                            movimiento={row}
                                            seleccionado={seleccionado === row.id}
                                            disabled={isEnviando}
                                            onSeleccionar={() => setSeleccionado(row.id === seleccionado ? null : row.id)}
                                            onEnviarAPuente={() => setPuenteMovimiento(row)}
                                            onDesmarcar={() => row.marca_vigente && setMarcaADesmarcar(row.marca_vigente.id)}
                                        />
                                    ),
                            },
                        ]}
                        rows={movimientos}
                        getRowKey={(row) => row.id}
                        loading={isLoading}
                        emptyMessage="El extracto no tiene movimientos."
                        maxHeightClassName="max-h-[65vh]"
                    />
                </div>

                <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                    <h2 className="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">Libro (asientos candidatos)</h2>

                    <DataTable
                        columns={[
                            { key: 'fecha', label: 'Fecha' },
                            { key: 'tipo_comprobante', label: 'Comprobante', render: (row) => `${row.tipo_comprobante} ${row.consecutivo}` },
                            { key: 'descripcion', label: 'Descripción', render: (row) => row.descripcion ?? '—' },
                            { key: 'valor', label: 'Valor', render: (row) => formatMoney(Number(row.debito) > 0 ? row.debito : row.credito) },
                            {
                                key: 'acciones',
                                label: 'Acción',
                                render: (row) =>
                                    canConciliar && (
                                        <div className="flex flex-col items-start gap-1">
                                            {sugerenciaDelSeleccionado?.sugeridos.includes(row.id) && (
                                                <span className="rounded-full bg-success-50 px-2.5 py-0.5 text-xs font-medium text-success-700 dark:bg-success-500/15 dark:text-success-400">
                                                    Sugerido
                                                </span>
                                            )}
                                            <Button size="sm" variant="outline" disabled={seleccionado === null || isEnviando} onClick={() => void conciliar(row)}>
                                                Conciliar
                                            </Button>
                                        </div>
                                    ),
                            },
                        ]}
                        rows={asientos}
                        getRowKey={(row) => row.id}
                        loading={isLoading}
                        emptyMessage="No hay asientos candidatos en la ventana de fechas del extracto."
                        maxHeightClassName="max-h-[65vh]"
                    />
                </div>
            </div>

            <SendToBridgeModal
                isOpen={puenteMovimiento !== null}
                movimiento={puenteMovimiento}
                onClose={() => setPuenteMovimiento(null)}
                onSent={onExito}
                onError={onFallo}
            />

            <MotivoModal
                isOpen={marcaADesmarcar !== null}
                title="Desmarcar conciliación"
                description="El movimiento vuelve a quedar pendiente. Si estaba en la cuenta puente, el comprobante generado se reversa."
                confirmLabel="Desmarcar"
                isSubmitting={isEnviando}
                onClose={() => setMarcaADesmarcar(null)}
                onConfirm={(motivo) => void desmarcar(motivo)}
            />

            <CloseStatementModal
                isOpen={isCerrarOpen}
                extracto={data ? { id: data.extracto.id, saldo_actual: data.extracto.saldo_actual } : null}
                pendientes={pendientes}
                onClose={() => setIsCerrarOpen(false)}
                onClosed={onExito}
                onError={onFallo}
            />

            <MotivoModal
                isOpen={isReabrirOpen}
                title="Reabrir extracto"
                description="El extracto vuelve a quedar cargado y disponible para conciliar. El motivo queda registrado en la bitácora."
                confirmLabel="Reabrir"
                isSubmitting={isEnviando}
                onClose={() => setIsReabrirOpen(false)}
                onConfirm={(motivo) => void reabrir(motivo)}
            />
        </div>
    );
};

type AccionesMovimientoProps = {
    movimiento: ApiExtractoMovimiento;
    seleccionado: boolean;
    disabled: boolean;
    onSeleccionar: () => void;
    onEnviarAPuente: () => void;
    onDesmarcar: () => void;
};

const AccionesMovimiento: FC<AccionesMovimientoProps> = ({ movimiento, seleccionado, disabled, onSeleccionar, onEnviarAPuente, onDesmarcar }) => {
    if (movimiento.estado !== 'pendiente') {
        return (
            <Button size="sm" variant="outline" disabled={disabled} onClick={onDesmarcar}>
                Desmarcar
            </Button>
        );
    }

    return (
        <div className="flex flex-col items-start gap-2">
            <Button size="sm" variant={seleccionado ? 'primary' : 'outline'} disabled={disabled} onClick={onSeleccionar}>
                {seleccionado ? 'Seleccionado' : 'Seleccionar'}
            </Button>
            <Button size="sm" variant="outline" disabled={disabled} onClick={onEnviarAPuente}>
                Enviar a puente
            </Button>
        </div>
    );
};
