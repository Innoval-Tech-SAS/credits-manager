import type { FC } from 'react';
import { useState } from 'react';

import { usePermission } from '../../auth/hooks/use-permission';
import { ExportButton } from '../../shared/components/export-button';
import { useResource } from '../../shared/hooks/use-resource';
import { formatMoney } from '../../shared/utils/format-money';
import type { ApiExpenseCostCenterReport } from '../types/api-expense-cost-center-report';
import { Alert } from '@ui/alert/components/alert';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { DataTable } from '@ui/table/components/data-table';

const mesActual = () => new Date().toISOString().slice(0, 7);

/** Gastos causados por mes y centro de costo. Los anulados no entran; los anteriores a la obligatoriedad salen sin centro. */
export const ExpenseCostCenterReportPage: FC = () => {
    const canExport = usePermission('gastos', 'exportar');
    const [desde, setDesde] = useState(() => `${new Date().getFullYear()}-01`);
    const [hasta, setHasta] = useState(mesActual);
    const [exportMessage, setExportMessage] = useState<string | null>(null);
    const [exportError, setExportError] = useState<string | null>(null);

    const { data, isLoading, error } = useResource<ApiExpenseCostCenterReport>(
        desde && hasta && desde <= hasta ? `/gastos/reporte-centro-costo?desde=${desde}&hasta=${hasta}` : null,
    );
    const filas = data?.filas ?? [];

    return (
        <div className="flex min-h-full flex-col gap-6 pb-6">
            <PageBreadcrumb pageTitle="Gastos por centro de costo" />

            <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div>
                        <Label htmlFor="desde">Desde (mes)</Label>
                        <Input id="desde" type="month" value={desde} onChange={(event) => setDesde(event.target.value)} />
                    </div>
                    <div>
                        <Label htmlFor="hasta">Hasta (mes)</Label>
                        <Input id="hasta" type="month" value={hasta} onChange={(event) => setHasta(event.target.value)} />
                    </div>
                </div>

                {canExport && (
                    <div className="mt-6 flex justify-end">
                        <ExportButton
                            endpoint="/gastos/reporte-centro-costo/exportar"
                            filters={{ desde, hasta }}
                            disabled={filas.length === 0}
                            onRequested={() => {
                                setExportError(null);
                                setExportMessage('Exportación solicitada. Se genera en segundo plano y te avisaremos cuando esté lista para descargar.');
                            }}
                            onError={(message) => {
                                setExportMessage(null);
                                setExportError(message);
                            }}
                        />
                    </div>
                )}

                {exportMessage && <div className="mt-4"><Alert variant="success" title="Listo" message={exportMessage} onDismiss={() => setExportMessage(null)} /></div>}
                {exportError && <div className="mt-4"><Alert variant="danger" title="No se pudo exportar" message={exportError} onDismiss={() => setExportError(null)} /></div>}
            </div>

            {error && <Alert variant="danger" title="No se pudo consultar" message={error} />}

            <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                <DataTable
                    columns={[
                        { key: 'mes', label: 'Mes' },
                        {
                            key: 'centro',
                            label: 'Centro de costo',
                            render: (row) => (row.centro_costo ? `${row.centro_costo.codigo} - ${row.centro_costo.nombre}` : 'Sin centro de costo'),
                        },
                        { key: 'cantidad', label: 'Gastos', render: (row) => String(row.cantidad) },
                        { key: 'valor_bruto', label: 'Valor bruto', render: (row) => formatMoney(row.valor_bruto) },
                        { key: 'valor_neto', label: 'Valor neto', render: (row) => formatMoney(row.valor_neto) },
                    ]}
                    rows={filas}
                    getRowKey={(row) => `${row.mes}-${row.centro_costo?.id ?? 'sin'}`}
                    loading={isLoading}
                    emptyMessage="Sin gastos causados en el rango."
                />

                {data && (
                    <div className="mt-4 flex flex-wrap justify-end gap-6 border-t border-gray-100 pt-4 text-sm font-semibold text-gray-800 dark:border-gray-800 dark:text-white/90">
                        <span>Gastos: {data.total_cantidad}</span>
                        <span>Bruto: {formatMoney(data.total_bruto)}</span>
                        <span>Neto: {formatMoney(data.total_neto)}</span>
                    </div>
                )}
            </div>
        </div>
    );
};
