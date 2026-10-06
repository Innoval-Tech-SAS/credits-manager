import type { FC } from 'react';
import { useState } from 'react';

import { useResource } from '../../shared/hooks/use-resource';
import { formatMoney } from '../../shared/utils/format-money';
import type { ApiOpeningReconciliation } from '../types/api-opening-reconciliation';
import { Alert } from '@ui/alert/components/alert';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { DatePicker } from '@ui/forms/components/date-picker';
import { Label } from '@ui/forms/components/label';
import { DataTable } from '@ui/table/components/data-table';

/**
 * Saldos cargados en la importación contra el balance de prueba a la misma fecha, cuenta por cuenta.
 * Antes de producción la diferencia debe ser cero en todas las cuentas.
 */
export const OpeningReconciliationPage: FC = () => {
    const [hasta, setHasta] = useState(() => new Date().toISOString().slice(0, 10));

    const { data, isLoading, error } = useResource<ApiOpeningReconciliation>(`/contabilidad/conciliacion-apertura?hasta=${hasta}`);
    const filas = data?.filas ?? [];

    return (
        <div className="flex min-h-full flex-col gap-6 pb-6">
            <PageBreadcrumb pageTitle="Conciliación de apertura" />

            <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                <div className="max-w-xs">
                    <Label htmlFor="hasta">Fecha de corte</Label>
                    <DatePicker id="hasta" value={hasta} onChange={setHasta} />
                </div>
                <p className="mt-4 text-sm text-gray-500 dark:text-gray-400">
                    Compara los saldos de apertura cargados con el balance de prueba a esa fecha. Una diferencia indica un saldo sin contabilizar o un
                    movimiento sobre la cuenta equivocada.
                </p>
            </div>

            {error && <Alert variant="danger" title="No se pudo conciliar" message={error} />}

            {data && (
                <Alert
                    variant={data.cuadra ? 'success' : 'danger'}
                    title={data.cuadra ? 'Conciliado' : 'Con diferencias'}
                    message={
                        data.cuadra
                            ? 'Los saldos cargados coinciden con el balance de prueba en todas las cuentas.'
                            : `${data.cuentas_con_diferencia} cuenta(s) con diferencia entre lo cargado y el balance.`
                    }
                />
            )}

            <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                <DataTable
                    columns={[
                        { key: 'codigo', label: 'Código' },
                        { key: 'nombre', label: 'Cuenta' },
                        { key: 'cargado_debito', label: 'Cargado débito', render: (row) => formatMoney(row.cargado_debito) },
                        { key: 'cargado_credito', label: 'Cargado crédito', render: (row) => formatMoney(row.cargado_credito) },
                        { key: 'balance_debito', label: 'Balance débito', render: (row) => formatMoney(row.balance_debito) },
                        { key: 'balance_credito', label: 'Balance crédito', render: (row) => formatMoney(row.balance_credito) },
                        {
                            key: 'diferencia',
                            label: 'Diferencia',
                            render: (row) => (
                                <span className={parseFloat(row.diferencia) === 0 ? '' : 'font-semibold text-error-600 dark:text-error-400'}>
                                    {formatMoney(row.diferencia)}
                                </span>
                            ),
                        },
                    ]}
                    rows={filas}
                    getRowKey={(row) => row.codigo}
                    loading={isLoading}
                    emptyMessage="No hay saldos cargados ni movimientos a esta fecha."
                />

                {data && (
                    <div className="mt-4 flex flex-wrap justify-end gap-6 border-t border-gray-100 pt-4 text-sm font-semibold text-gray-800 dark:border-gray-800 dark:text-white/90">
                        <span>Cargado: {formatMoney(data.total_cargado_debito)} / {formatMoney(data.total_cargado_credito)}</span>
                        <span>Balance: {formatMoney(data.total_balance_debito)} / {formatMoney(data.total_balance_credito)}</span>
                    </div>
                )}
            </div>
        </div>
    );
};
