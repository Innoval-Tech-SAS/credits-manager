import type { FC } from 'react';
import { useState } from 'react';

import { useResource } from '../../shared/hooks/use-resource';
import { ReportExportButton } from '../components/report-export-button';
import { formatDateEs } from '../../shared/utils/format-date-es';
import { formatMoney } from '../../shared/utils/format-money';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { DatePicker } from '@ui/forms/components/date-picker';
import { Label } from '@ui/forms/components/label';
import { DataTable } from '@ui/table/components/data-table';
import type { ApiJournal } from '../types/api-journal';

function primerDiaDelMes(): string {
    const hoy = new Date();
    return `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, '0')}-01`;
}

export const JournalPage: FC = () => {
    const [desde, setDesde] = useState(primerDiaDelMes());
    const [hasta, setHasta] = useState(() => new Date().toISOString().slice(0, 10));

    const { data, isLoading } = useResource<ApiJournal>(`/contabilidad/libro-diario?desde=${desde}&hasta=${hasta}`);
    const movimientos = data?.movimientos ?? [];

    return (
        <div className="flex min-h-full flex-col gap-6 pb-6">
            <PageBreadcrumb pageTitle="Libro diario" />

            <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div>
                        <Label htmlFor="desde">Desde</Label>
                        <DatePicker id="desde" value={desde} onChange={setDesde} />
                    </div>
                    <div>
                        <Label htmlFor="hasta">Hasta</Label>
                        <DatePicker id="hasta" value={hasta} onChange={setHasta} />
                    </div>
                </div>

                <div className="mt-6">
                    <ReportExportButton endpoint="/contabilidad/libro-diario/exportar" filters={{ desde, hasta }} />
                </div>
            </div>

            <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                <DataTable
                    columns={[
                        { key: 'fecha', label: 'Fecha', render: (row) => formatDateEs(row.fecha) },
                        { key: 'comp', label: 'Comp.', render: (row) => `${row.tipo}-${row.consecutivo}` },
                        { key: 'cuenta', label: 'Cuenta', render: (row) => `${row.cuenta_codigo} ${row.cuenta_nombre}` },
                        { key: 'descripcion', label: 'Descripción', render: (row) => row.descripcion ?? '—' },
                        { key: 'debito', label: 'Débito', render: (row) => (parseFloat(row.debito) > 0 ? formatMoney(row.debito) : '') },
                        { key: 'credito', label: 'Crédito', render: (row) => (parseFloat(row.credito) > 0 ? formatMoney(row.credito) : '') },
                    ]}
                    rows={movimientos}
                    getRowKey={(row) => `${row.fecha}-${row.consecutivo}-${row.cuenta_codigo}-${row.debito}-${row.credito}`}
                    loading={isLoading}
                    emptyMessage="Sin movimientos en el rango."
                    maxHeightClassName="max-h-[65vh]"
                />

                {data && (
                    <div className="mt-4 flex justify-end gap-6 border-t border-gray-100 pt-4 text-sm font-semibold text-gray-800 dark:border-gray-800 dark:text-white/90">
                        <span>Total débito: {formatMoney(data.total_debito)}</span>
                        <span>Total crédito: {formatMoney(data.total_credito)}</span>
                    </div>
                )}
            </div>
        </div>
    );
};
