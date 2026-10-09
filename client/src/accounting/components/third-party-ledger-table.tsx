import type { FC } from 'react';
import { useState } from 'react';
import { useNavigate } from 'react-router';

import { ArrowRightCircle, Wallet } from 'lucide-react';
import { usePermission } from '../../auth/hooks/use-permission';
import { usePaginatedResource } from '../../shared/hooks/use-paginated-resource';
import { useResource } from '../../shared/hooks/use-resource';
import { formatDateEs } from '../../shared/utils/format-date-es';
import { formatMoney } from '../../shared/utils/format-money';
import { ThirdPartySelector } from '../../third-parties/components/third-party-selector';
import type { ApiThirdParty } from '../../third-parties/types/api-third-party';
import { Alert } from '@ui/alert/components/alert';
import { Button } from '@ui/button/components/button';
import { Checkbox } from '@ui/forms/components/checkbox';
import { DatePicker } from '@ui/forms/components/date-picker';
import { Label } from '@ui/forms/components/label';
import { Select } from '@ui/forms/components/select';
import { ToggleSwitchInput } from '@ui/forms/components/toggle-switch-input';
import { DataTable } from '@ui/table/components/data-table';
import { Pagination } from '@ui/table/components/pagination';
import { CarteraAgingStrip } from './cartera-aging-strip';
import { RegisterOutflowModal } from './register-outflow-modal';
import { DOCUMENT_STATUS_BADGE_CLASSES, DOCUMENT_STATUS_LABELS } from '../constants/document-status-badge-classes';
import type { ApiCarteraDocument, ApiCarteraEdades } from '../types/api-cartera-document';
import type { ThirdPartyLedgerTableProps } from '../props/third-party-ledger-table-props';

const hoyBogota = () => new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Bogota' }).format(new Date());

const ESTADO_OPTIONS = [
    { value: 'pendiente', label: 'Pendiente' },
    { value: 'abonado_parcial', label: 'Abonado parcial' },
    { value: 'vencido', label: 'Vencido' },
    { value: 'cancelado', label: 'Pagado' },
];

export const ThirdPartyLedgerTable: FC<ThirdPartyLedgerTableProps> = ({ naturaleza }) => {
    const navigate = useNavigate();
    const puedeRegistrarEgreso = usePermission('egresos', 'crear');

    const [tercero, setTercero] = useState<ApiThirdParty | null>(null);
    const [todos, setTodos] = useState(false);
    const [desde, setDesde] = useState('');
    const [hasta, setHasta] = useState('');
    const [estado, setEstado] = useState('');
    const [fechaCorte, setFechaCorte] = useState(hoyBogota());
    const [seleccionPago, setSeleccionPago] = useState<Record<number, ApiCarteraDocument>>({});
    const [egresoModalAbierto, setEgresoModalAbierto] = useState(false);
    const [mensaje, setMensaje] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);

    const terceroId = todos ? '' : tercero ? String(tercero.id) : '';
    const permiteSeleccionMultiple = naturaleza === 'por_pagar' && puedeRegistrarEgreso && !todos && tercero !== null;

    const cambiarTercero = (nuevo: ApiThirdParty | null) => {
        setTercero(nuevo);
        setSeleccionPago({});
    };

    const cambiarTodos = (valor: boolean) => {
        setTodos(valor);
        setSeleccionPago({});
    };

    const alternarSeleccion = (documento: ApiCarteraDocument, marcado: boolean) => {
        setSeleccionPago((anterior) => {
            const copia = { ...anterior };
            if (marcado) {
                copia[documento.id] = documento;
            } else {
                delete copia[documento.id];
            }
            return copia;
        });
    };

    const documentosSeleccionados = Object.values(seleccionPago);

    const { items, isLoading, page, setPage, totalPages, totalItems, refetch } = usePaginatedResource<ApiCarteraDocument>(
        todos || tercero ? '/cartera-terceros' : null,
        { naturaleza, tercero_id: terceroId, desde, hasta, estado, fecha_corte: fechaCorte },
    );

    const { data: edadesData, isLoading: isLoadingEdades, refetch: refetchEdades } = useResource<{ edades: ApiCarteraEdades }>(
        todos || tercero
            ? `/cartera-terceros/edades?naturaleza=${naturaleza}${terceroId ? `&tercero_id=${terceroId}` : ''}&fecha_corte=${fechaCorte}`
            : null,
    );

    const totalSaldoPagina = items.reduce((suma, documento) => suma + Number(documento.saldo), 0);

    const verDocumentoOrigen = (documento: ApiCarteraDocument) => {
        if (documento.modulo_origen === 'facturacion' && documento.origen_id) {
            navigate(`/facturacion/${documento.origen_id}`);
        }
    };

    return (
        <div className="flex flex-col gap-6">
            <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="sm:col-span-2 lg:col-span-1">
                        <Label htmlFor="tercero">Tercero</Label>
                        <ThirdPartySelector id="tercero" selected={tercero} onSelect={cambiarTercero} />
                        <div className="mt-2">
                            <ToggleSwitchInput id="todos-terceros" label="Todos los terceros" checked={todos} onChange={cambiarTodos} />
                        </div>
                    </div>
                    <div>
                        <Label htmlFor="desde">Desde</Label>
                        <DatePicker id="desde" value={desde} onChange={setDesde} />
                    </div>
                    <div>
                        <Label htmlFor="hasta">Hasta</Label>
                        <DatePicker id="hasta" value={hasta} onChange={setHasta} />
                    </div>
                    <div>
                        <Label htmlFor="fecha-corte">Fecha de corte</Label>
                        <DatePicker id="fecha-corte" value={fechaCorte} onChange={setFechaCorte} />
                    </div>
                    <div>
                        <Label htmlFor="estado">Estado</Label>
                        <Select options={ESTADO_OPTIONS} placeholder="Todos" placeholderSelectable onChange={setEstado} />
                    </div>
                </div>
            </div>

            {(todos || tercero) && (
                <>
                    <CarteraAgingStrip edades={edadesData?.edades ?? null} isLoading={isLoadingEdades} />

                    {mensaje && <Alert variant="success" title="Listo" message={mensaje} onDismiss={() => setMensaje(null)} />}
                    {error && <Alert variant="danger" title="Error" message={error} onDismiss={() => setError(null)} />}

                    {permiteSeleccionMultiple && documentosSeleccionados.length > 0 && (
                        <div className="flex flex-col items-start gap-3 rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/3 sm:flex-row sm:items-center sm:justify-between">
                            <p className="text-sm text-gray-600 dark:text-gray-300">
                                {documentosSeleccionados.length} {documentosSeleccionados.length === 1 ? 'documento marcado' : 'documentos marcados'} — total{' '}
                                {formatMoney(documentosSeleccionados.reduce((suma, documento) => suma + Number(documento.saldo), 0))}
                            </p>
                            <Button size="sm" startIcon={<Wallet className="size-4" />} onClick={() => setEgresoModalAbierto(true)}>
                                Pagar seleccionados
                            </Button>
                        </div>
                    )}

                    <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                        <DataTable
                            columns={[
                                ...(permiteSeleccionMultiple
                                    ? [
                                          {
                                              key: 'seleccion',
                                              label: '',
                                              render: (documento: ApiCarteraDocument) =>
                                                  Number(documento.saldo) <= 0 ? null : (
                                                      <Checkbox
                                                          checked={documento.id in seleccionPago}
                                                          onChange={(marcado) => alternarSeleccion(documento, marcado)}
                                                      />
                                                  ),
                                          },
                                      ]
                                    : []),
                                { key: 'tipo_documento', label: 'Tipo' },
                                { key: 'numero_documento', label: 'Número' },
                                { key: 'fecha', label: 'Fecha', render: (documento) => formatDateEs(documento.fecha) },
                                {
                                    key: 'fecha_vencimiento',
                                    label: 'Vencimiento',
                                    render: (documento) => (
                                        <div>
                                            <p>{documento.fecha_vencimiento ? formatDateEs(documento.fecha_vencimiento) : '—'}</p>
                                            {documento.dias_mora > 0 && (
                                                <p className="text-xs text-error-600 dark:text-error-400">{documento.dias_mora} días de mora</p>
                                            )}
                                            {!documento.dias_mora && documento.dias_por_vencer !== null && documento.dias_por_vencer > 0 && (
                                                <p className="text-xs text-gray-400 dark:text-gray-500">Faltan {documento.dias_por_vencer} días</p>
                                            )}
                                        </div>
                                    ),
                                },
                                { key: 'concepto', label: 'Concepto' },
                                {
                                    key: 'plan_cuenta',
                                    label: 'Cuenta',
                                    render: (documento) => (documento.plan_cuenta ? `${documento.plan_cuenta.codigo} - ${documento.plan_cuenta.nombre}` : '—'),
                                },
                                { key: 'valor_original', label: 'Valor original', render: (documento) => formatMoney(documento.valor_original) },
                                { key: 'abonado', label: 'Abonado', render: (documento) => formatMoney(documento.abonado) },
                                {
                                    key: 'saldo',
                                    label: 'Saldo',
                                    render: (documento) => <span className="font-semibold text-gray-800 dark:text-white/90">{formatMoney(documento.saldo)}</span>,
                                },
                                {
                                    key: 'estado',
                                    label: 'Estado',
                                    render: (documento) => (
                                        <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${DOCUMENT_STATUS_BADGE_CLASSES[documento.estado]}`}>
                                            {DOCUMENT_STATUS_LABELS[documento.estado]}
                                        </span>
                                    ),
                                },
                                {
                                    key: 'acciones',
                                    label: 'Acciones',
                                    render: (documento) =>
                                        Number(documento.saldo) <= 0 ? null : naturaleza === 'por_pagar' ? (
                                            puedeRegistrarEgreso && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    startIcon={<Wallet className="size-4" />}
                                                    onClick={() => {
                                                        setSeleccionPago({ [documento.id]: documento });
                                                        setEgresoModalAbierto(true);
                                                    }}
                                                >
                                                    Pagar
                                                </Button>
                                            )
                                        ) : (
                                            documento.modulo_origen === 'facturacion' && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    startIcon={<ArrowRightCircle className="size-4" />}
                                                    onClick={() => verDocumentoOrigen(documento)}
                                                >
                                                    Ver factura
                                                </Button>
                                            )
                                        ),
                                },
                            ]}
                            rows={items}
                            getRowKey={(row) => row.id}
                            loading={isLoading}
                            emptyMessage="Sin documentos para los filtros seleccionados."
                        />

                        {!isLoading && items.length > 0 && (
                            <div className="mt-3 flex justify-end border-t border-gray-100 pt-3 text-sm dark:border-gray-800">
                                <span className="text-gray-500 dark:text-gray-400">Total saldo (página actual):&nbsp;</span>
                                <span className="font-semibold text-gray-800 dark:text-white/90">{formatMoney(totalSaldoPagina)}</span>
                            </div>
                        )}

                        <div className="mt-4">
                            <Pagination page={page} totalPages={totalPages} onPageChange={setPage} />
                        </div>
                        <p className="mt-2 text-xs text-gray-400 dark:text-gray-500">
                            {totalItems} {totalItems === 1 ? 'documento' : 'documentos'} en total.
                        </p>
                    </div>
                </>
            )}

            {egresoModalAbierto && documentosSeleccionados.length > 0 && (
                <RegisterOutflowModal
                    isOpen
                    terceroId={documentosSeleccionados[0].tercero.id}
                    documentos={documentosSeleccionados.map((documento) => ({
                        id: documento.id,
                        numero_documento: documento.numero_documento,
                        saldo: documento.saldo,
                    }))}
                    onClose={() => setEgresoModalAbierto(false)}
                    onRegistrado={(texto) => {
                        setMensaje(texto);
                        setEgresoModalAbierto(false);
                        setSeleccionPago({});
                        refetch();
                        refetchEdades();
                    }}
                    onError={setError}
                />
            )}
        </div>
    );
};
