import type { FC } from 'react';
import { useState } from 'react';

import { useResource } from '../../shared/hooks/use-resource';
import { ReportExportButton } from '../components/report-export-button';
import { formatMoney } from '../../shared/utils/format-money';
import { Alert } from '@ui/alert/components/alert';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { DatePicker } from '@ui/forms/components/date-picker';
import { Label } from '@ui/forms/components/label';
import { FinancialStatementTableRows } from '../components/financial-statement-table-rows';
import type { ApiFinancialStatements } from '../types/api-financial-statements';

function primerDiaDelAnio(): string {
    return `${new Date().getFullYear()}-01-01`;
}

export const FinancialStatementsPage: FC = () => {
    const [desde, setDesde] = useState(primerDiaDelAnio());
    const [hasta, setHasta] = useState(() => new Date().toISOString().slice(0, 10));

    const { data } = useResource<ApiFinancialStatements>(`/contabilidad/estados-financieros?desde=${desde}&hasta=${hasta}`);

    return (
        <div className="flex min-h-full flex-col gap-6 pb-6">
            <PageBreadcrumb pageTitle="Estados financieros" />

            <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div>
                        <Label htmlFor="desde">Desde (P&amp;G)</Label>
                        <DatePicker id="desde" value={desde} onChange={setDesde} />
                    </div>
                    <div>
                        <Label htmlFor="hasta">Corte al</Label>
                        <DatePicker id="hasta" value={hasta} onChange={setHasta} />
                    </div>
                </div>

                <div className="mt-6">
                    <ReportExportButton
                        endpoint="/contabilidad/estados-financieros/exportar"
                        pdfEndpoint="/contabilidad/estados-financieros/pdf"
                        pdfFilename="estados-financieros.pdf"
                        filters={{ desde, hasta }}
                    />
                </div>
            </div>

            {data && (
                <>
                    <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                        <h2 className="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">
                            Estado de resultados ({desde} a {hasta})
                        </h2>
                        <div className="custom-scrollbar overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <tbody>
                                    <FinancialStatementTableRows filas={data.estado_resultados.ingresos} />
                                    <tr className="border-b border-gray-100 font-semibold text-gray-800 dark:border-gray-800 dark:text-white/90">
                                        <td className="px-4 py-2">Total ingresos</td>
                                        <td className="px-4 py-2">{formatMoney(data.estado_resultados.total_ingresos)}</td>
                                    </tr>
                                    <FinancialStatementTableRows filas={data.estado_resultados.gastos_costos} negativo />
                                    <tr className="border-b border-gray-100 font-semibold text-gray-800 dark:border-gray-800 dark:text-white/90">
                                        <td className="px-4 py-2">Total costos y gastos</td>
                                        <td className="px-4 py-2">({formatMoney(data.estado_resultados.total_gastos)})</td>
                                    </tr>
                                    <tr className="font-semibold text-gray-800 dark:text-white/90">
                                        <td className="px-4 py-2">Utilidad / pérdida del período</td>
                                        <td className="px-4 py-2">{formatMoney(data.estado_resultados.utilidad)}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                        <h2 className="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">
                            Estado de situación financiera (corte {hasta})
                        </h2>
                        <div className="custom-scrollbar overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <tbody>
                                    <tr>
                                        <td colSpan={2} className="px-4 py-2 font-semibold text-gray-800 dark:text-white/90">
                                            Activos
                                        </td>
                                    </tr>
                                    <FinancialStatementTableRows filas={data.situacion_financiera.activos} />
                                    <tr className="border-b border-gray-100 font-semibold text-gray-800 dark:border-gray-800 dark:text-white/90">
                                        <td className="px-4 py-2">Total activos</td>
                                        <td className="px-4 py-2">{formatMoney(data.situacion_financiera.total_activos)}</td>
                                    </tr>
                                    <tr>
                                        <td colSpan={2} className="px-4 py-2 font-semibold text-gray-800 dark:text-white/90">
                                            Pasivos
                                        </td>
                                    </tr>
                                    <FinancialStatementTableRows filas={data.situacion_financiera.pasivos} />
                                    <tr className="border-b border-gray-100 font-semibold text-gray-800 dark:border-gray-800 dark:text-white/90">
                                        <td className="px-4 py-2">Total pasivos</td>
                                        <td className="px-4 py-2">{formatMoney(data.situacion_financiera.total_pasivos)}</td>
                                    </tr>
                                    <tr>
                                        <td colSpan={2} className="px-4 py-2 font-semibold text-gray-800 dark:text-white/90">
                                            Patrimonio
                                        </td>
                                    </tr>
                                    <FinancialStatementTableRows filas={data.situacion_financiera.patrimonio} />
                                    <tr>
                                        <td className="px-4 py-2 text-gray-700 dark:text-gray-300">Utilidad del período (P&amp;G)</td>
                                        <td className="px-4 py-2 text-gray-700 dark:text-gray-300">
                                            {formatMoney(data.situacion_financiera.utilidad)}
                                        </td>
                                    </tr>
                                    <tr className="font-semibold text-gray-800 dark:text-white/90">
                                        <td className="px-4 py-2">Total pasivo + patrimonio</td>
                                        <td className="px-4 py-2">
                                            {formatMoney(
                                                Number(data.situacion_financiera.total_pasivos) +
                                                    Number(data.situacion_financiera.total_patrimonio) +
                                                    Number(data.situacion_financiera.utilidad),
                                            )}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div className="mt-4">
                            {data.situacion_financiera.cuadra ? (
                                <Alert variant="success" title="Cuadrado" message="Activo = Pasivo + Patrimonio. La igualdad contable se cumple." />
                            ) : (
                                <Alert
                                    variant="danger"
                                    title="Atención"
                                    message="El balance general no cuadra con el activo. Revise la parametrización de cuentas y comprobantes."
                                />
                            )}
                        </div>
                    </div>
                </>
            )}
        </div>
    );
};
