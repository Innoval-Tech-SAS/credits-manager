import type { FC } from 'react';
import { useState } from 'react';

import { useResource } from '../../shared/hooks/use-resource';
import { ReportExportButton } from '../components/report-export-button';
import { formatMoney } from '../../shared/utils/format-money';
import { Alert } from '@ui/alert/components/alert';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { DatePicker } from '@ui/forms/components/date-picker';
import { Label } from '@ui/forms/components/label';
import { DataTable } from '@ui/table/components/data-table';
import type { ApiTrialBalance } from '../types/api-trial-balance';

export const TrialBalancePage: FC = () => {
    const [hasta, setHasta] = useState(() => new Date().toISOString().slice(0, 10));

    const { data, isLoading } = useResource<ApiTrialBalance>(`/contabilidad/balance-prueba?hasta=${hasta}`);
    const saldos = data?.saldos ?? [];
    const cuadra = data ? Math.round(Number(data.total_debito) * 100) === Math.round(Number(data.total_credito) * 100) : true;

    return (
        <div className="flex min-h-full flex-col gap-6 pb-6">
            <PageBreadcrumb pageTitle="Balance de prueba" />

            <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                <div className="max-w-xs">
                    <Label htmlFor="hasta">Corte al</Label>
                    <DatePicker id="hasta" value={hasta} onChange={setHasta} />
                </div>

                <div className="mt-6">
                    <ReportExportButton
                        endpoint="/contabilidad/balance-prueba/exportar"
                        pdfEndpoint="/contabilidad/balance-prueba/pdf"
                        pdfFilename="balance-de-prueba.pdf"
                        filters={{ hasta }}
                    />
                </div>
            </div>

            <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                <DataTable
                    columns={[
                        { key: 'codigo', label: 'Código' },
                        { key: 'nombre', label: 'Cuenta' },
                        { key: 'debitos', label: 'Débitos', render: (row) => formatMoney(row.debitos) },
                        { key: 'creditos', label: 'Créditos', render: (row) => formatMoney(row.creditos) },
                        { key: 'saldo', label: 'Saldo', render: (row) => formatMoney(row.saldo) },
                    ]}
                    rows={saldos}
                    getRowKey={(row) => row.codigo}
                    loading={isLoading}
                    emptyMessage="Sin movimientos."
                    maxHeightClassName="max-h-[65vh]"
                />

                {data && (
                    <div className="mt-4 flex justify-end gap-6 border-t border-gray-100 pt-4 text-sm font-semibold text-gray-800 dark:border-gray-800 dark:text-white/90">
                        <span>Total: {formatMoney(data.total_debito)}</span>
                        <span>{formatMoney(data.total_credito)}</span>
                    </div>
                )}

                {data && (
                    <div className="mt-4">
                        {cuadra ? (
                            <Alert variant="success" title="Cuadrado" message="El balance cuadra: total débitos = total créditos." />
                        ) : (
                            <Alert variant="danger" title="Atención" message="El balance no está cuadrado. Verifique comprobantes pendientes." />
                        )}
                    </div>
                )}
            </div>
        </div>
    );
};
