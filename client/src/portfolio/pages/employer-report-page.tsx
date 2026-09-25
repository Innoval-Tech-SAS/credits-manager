import type { FC } from 'react';
import { useState } from 'react';

import { usePermission } from '../../auth/hooks/use-permission';
import { ExportButton } from '../../shared/components/export-button';
import { useResource } from '../../shared/hooks/use-resource';
import { formatMoney } from '../../shared/utils/format-money';
import { Alert } from '@ui/alert/components/alert';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { Select } from '@ui/forms/components/select';
import { Spinner } from '@ui/spinner/components/spinner';
import { INSTALLMENT_STATUS_CLASSES, INSTALLMENT_STATUS_LABEL } from '../../credits/constants/installment-status-styles';
import type { ApiEmployerReport } from '../types/api-employer-report';
import { formatPeriodoEs } from '../utils/format-periodo-es';

export const EmployerReportPage: FC = () => {
    /** null = todavía no hay selección explícita del usuario; usa el default que resuelve el servidor. */
    const [empleadorId, setEmpleadorId] = useState<number | null>(null);
    const [exportMessage, setExportMessage] = useState<string | null>(null);
    const [exportError, setExportError] = useState<string | null>(null);
    const canExport = usePermission('cartera', 'exportar');
    const canPrint = usePermission('cartera', 'imprimir_reporte_empleador');

    const params = new URLSearchParams();
    if (empleadorId) {
        params.set('empleador_id', String(empleadorId));
    }

    const { data: reporte, error } = useResource<ApiEmployerReport>(`/cartera/reporte-empleador?${params.toString()}`);

    if (error) {
        return (
            <div className="rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                {error}
            </div>
        );
    }

    if (!reporte) {
        return (
            <div className="flex justify-center py-12">
                <Spinner size="lg" />
            </div>
        );
    }

    const { empleadores, empleador_seleccionado: empleadorSeleccionado, meses, filas, totales_por_mes: totalesPorMes } = reporte;
    const nombreSeleccionado = empleadores.find((empleador) => empleador.id === empleadorSeleccionado)?.nombre;

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Reporte por empleador" />

            {exportMessage && (
                <div className="mb-4 print:hidden">
                    <Alert variant="success" title="Listo" message={exportMessage} onDismiss={() => setExportMessage(null)} />
                </div>
            )}
            {exportError && (
                <div className="mb-4 print:hidden">
                    <Alert variant="danger" title="No se pudo exportar" message={exportError} onDismiss={() => setExportError(null)} />
                </div>
            )}

            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between print:hidden">
                <div className="w-full max-w-sm rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                    {empleadores.length === 0 ? (
                        <p className="text-sm text-gray-500 dark:text-gray-400">No hay clientes con empleador registrado.</p>
                    ) : (
                        <Select
                            key={empleadorSeleccionado ?? ''}
                            options={empleadores.map((empleador) => ({ value: String(empleador.id), label: empleador.nombre }))}
                            defaultValue={empleadorSeleccionado ? String(empleadorSeleccionado) : ''}
                            onChange={(value) => setEmpleadorId(value ? Number(value) : null)}
                        />
                    )}
                </div>

                {empleadorSeleccionado && (canExport || canPrint) && (
                    <ExportButton
                        endpoint="/cartera/reporte-empleador/exportar"
                        filters={{ empleador_id: String(empleadorSeleccionado) }}
                        formats={canExport ? ['xlsx'] : []}
                        pdfEndpoint={canPrint ? '/cartera/reporte-empleador/pdf' : undefined}
                        pdfFilename="relacion-descuento.pdf"
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
                )}
            </div>

            {empleadorSeleccionado && (
                <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3 print:border-0 print:p-0">
                    <h2 className="mb-4 text-lg font-semibold text-gray-800 dark:text-white/90">
                        Relación de descuento — {nombreSeleccionado}
                    </h2>

                    {filas.length === 0 ? (
                        <p className="text-sm text-gray-500 dark:text-gray-400">No hay créditos activos para clientes de este empleador.</p>
                    ) : (
                        <div className="custom-scrollbar overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-gray-200 text-left text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
                                        <th className="py-2 px-3 font-medium">Empleado</th>
                                        <th className="py-2 px-3 font-medium">Cuota</th>
                                        {meses.map((mes) => (
                                            <th key={mes} className="py-2 px-3 font-medium">
                                                {formatPeriodoEs(mes)}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {filas.map((fila) => (
                                        <tr key={fila.cliente_id} className="border-b border-gray-100 dark:border-gray-800">
                                            <td className="py-2 px-3 font-medium text-gray-800 dark:text-white/90">{fila.cliente_nombre}</td>
                                            <td className="py-2 px-3 text-gray-700 dark:text-gray-300">{formatMoney(fila.valor_cuota)}</td>
                                            {meses.map((mes) => {
                                                const cuota = fila.cuotas[mes];
                                                return (
                                                    <td key={mes} className="py-2 px-3 text-gray-700 dark:text-gray-300">
                                                        {cuota ? (
                                                            <span className="inline-flex items-center gap-1.5">
                                                                {formatMoney(cuota.valor)}
                                                                <span
                                                                    className={`rounded-full px-2 py-0.5 text-[10px] font-medium ${INSTALLMENT_STATUS_CLASSES[cuota.estado]}`}
                                                                >
                                                                    {INSTALLMENT_STATUS_LABEL[cuota.estado]}
                                                                </span>
                                                            </span>
                                                        ) : (
                                                            <span className="text-gray-400 dark:text-gray-600">—</span>
                                                        )}
                                                    </td>
                                                );
                                            })}
                                        </tr>
                                    ))}
                                    <tr className="border-t-2 border-gray-800 font-semibold text-gray-800 dark:border-white/90 dark:text-white/90">
                                        <td className="py-2 px-3" colSpan={2}>
                                            Total
                                        </td>
                                        {meses.map((mes) => (
                                            <td key={mes} className="py-2 px-3">
                                                {formatMoney(totalesPorMes[mes] ?? 0)}
                                            </td>
                                        ))}
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
};
