import type { FC } from 'react';
import { useState } from 'react';

import { useResource } from '../../shared/hooks/use-resource';
import { formatDateEs } from '../../shared/utils/format-date-es';
import { formatMoney } from '../../shared/utils/format-money';
import type { ApiClosingControls, ApiClosingControlsBalance } from '../types/api-closing-controls';
import { Alert } from '@ui/alert/components/alert';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { DatePicker } from '@ui/forms/components/date-picker';
import { Label } from '@ui/forms/components/label';
import { DataTable } from '@ui/table/components/data-table';

const BalanceSummary: FC<{ titulo: string; descripcion: string; datos?: ApiClosingControlsBalance }> = ({ titulo, descripcion, datos }) => (
    <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
        <h3 className="text-base font-semibold text-gray-800 dark:text-white/90">{titulo}</h3>
        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{descripcion}</p>
        {datos && (
            <div className="mt-4 flex flex-wrap gap-6 text-sm">
                <span>
                    Auxiliar: <span className="font-semibold text-gray-800 dark:text-white/90">{formatMoney(datos.total_auxiliar)}</span>
                </span>
                <span>
                    Balance: <span className="font-semibold text-gray-800 dark:text-white/90">{formatMoney(datos.total_balance)}</span>
                </span>
                <span className={datos.cuadra ? 'text-success-600 dark:text-success-400' : 'font-semibold text-error-600 dark:text-error-400'}>
                    Diferencia: {formatMoney(datos.diferencia)}
                </span>
            </div>
        )}
    </div>
);

/**
 * Controles de cierre mensual (6.6.3): auxiliar vs balance para CxC/CxP, saldos contrarios, documentos con estado
 * inconsistente con su saldo, y egresos/recibos sin conciliar — no bloquea nada, solo lista diferencias.
 */
export const ClosingControlsPage: FC = () => {
    const [hasta, setHasta] = useState(() => new Date().toISOString().slice(0, 10));

    const { data, isLoading } = useResource<ApiClosingControls>(`/contabilidad/controles-cierre?hasta=${hasta}`);

    return (
        <div className="flex min-h-full flex-col gap-6 pb-6">
            <PageBreadcrumb pageTitle="Controles de cierre mensual" />

            <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                <div className="max-w-xs">
                    <Label htmlFor="hasta">Fecha de corte</Label>
                    <DatePicker id="hasta" value={hasta} onChange={setHasta} />
                </div>
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <BalanceSummary
                    titulo="Cuentas por cobrar (1305)"
                    descripcion="El auxiliar por tercero y documento debe sumar igual que el saldo del balance."
                    datos={data?.cuentas_por_cobrar}
                />
                <BalanceSummary
                    titulo="Cuentas por pagar (2205 / 2335)"
                    descripcion="El auxiliar por tercero y documento debe sumar igual que el saldo del balance."
                    datos={data?.cuentas_por_pagar}
                />
            </div>

            {data && (
                <Alert
                    variant={data.saldos_contrarios.contador === 0 ? 'success' : 'danger'}
                    title="Saldos contrarios"
                    message={
                        data.saldos_contrarios.contador === 0
                            ? 'Ninguna cuenta de CxC/CxP tiene el saldo al revés de su naturaleza.'
                            : `${data.saldos_contrarios.contador} cuenta(s) con saldo contrario a su naturaleza.`
                    }
                />
            )}

            {data && data.saldos_contrarios.filas.length > 0 && (
                <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                    <DataTable
                        columns={[
                            { key: 'codigo', label: 'Código' },
                            { key: 'nombre', label: 'Cuenta' },
                            { key: 'saldo', label: 'Saldo', render: (row) => formatMoney(row.saldo) },
                        ]}
                        rows={data.saldos_contrarios.filas}
                        getRowKey={(row) => row.codigo}
                        loading={isLoading}
                        emptyMessage="Sin cuentas con saldo contrario."
                    />
                </div>
            )}

            {data && (
                <Alert
                    variant={data.documentos_inconsistentes.contador === 0 ? 'success' : 'danger'}
                    title="Documentos con estado inconsistente"
                    message={
                        data.documentos_inconsistentes.contador === 0
                            ? 'El saldo y el estado de todos los documentos de cartera coinciden.'
                            : `${data.documentos_inconsistentes.contador} documento(s) con saldo y estado desalineados.`
                    }
                />
            )}

            {data && data.documentos_inconsistentes.filas.length > 0 && (
                <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                    <DataTable
                        columns={[
                            { key: 'numero_documento', label: 'Documento' },
                            { key: 'concepto', label: 'Concepto' },
                            { key: 'estado', label: 'Estado' },
                            { key: 'saldo', label: 'Saldo', render: (row) => formatMoney(row.saldo) },
                        ]}
                        rows={data.documentos_inconsistentes.filas}
                        getRowKey={(row) => row.id}
                        loading={isLoading}
                        emptyMessage="Sin documentos inconsistentes."
                    />
                </div>
            )}

            {data && (
                <Alert
                    variant={data.conciliacion_bancaria.contador === 0 ? 'success' : 'danger'}
                    title="Conciliación bancaria"
                    message={
                        data.conciliacion_bancaria.contador === 0
                            ? 'Todos los egresos y recibos contabilizados tienen su contraparte en el extracto.'
                            : `${data.conciliacion_bancaria.contador} movimiento(s) de caja/bancos sin conciliar.`
                    }
                />
            )}

            {data && data.conciliacion_bancaria.filas.length > 0 && (
                <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                    <DataTable
                        columns={[
                            { key: 'fecha', label: 'Fecha', render: (row) => formatDateEs(row.fecha) },
                            { key: 'modulo_origen', label: 'Origen' },
                            { key: 'cuenta_codigo', label: 'Cuenta' },
                            { key: 'debito', label: 'Débito', render: (row) => formatMoney(row.debito) },
                            { key: 'credito', label: 'Crédito', render: (row) => formatMoney(row.credito) },
                        ]}
                        rows={data.conciliacion_bancaria.filas}
                        getRowKey={(row) => row.id}
                        loading={isLoading}
                        emptyMessage="Sin movimientos sin conciliar."
                    />
                </div>
            )}
        </div>
    );
};
