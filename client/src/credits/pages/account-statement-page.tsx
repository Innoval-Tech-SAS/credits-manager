import type { FC } from 'react';
import { useState } from 'react';
import { useParams } from 'react-router';
import { Download, Printer } from 'lucide-react';

import { usePermission } from '../../auth/hooks/use-permission';
import { PermissionExportButton } from '../../shared/components/permission-export-button';
import { useDownloadFile } from '../../shared/hooks/use-download-file';
import { useResource } from '../../shared/hooks/use-resource';
import { useCompany } from '../../company/hooks/use-company';
import { Alert } from '@ui/alert/components/alert';
import { Button } from '@ui/button/components/button';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { Spinner } from '@ui/spinner/components/spinner';
import { AccountStatementHeader } from '../components/account-statement-header';
import { AccountStatementInstallmentsTable } from '../components/account-statement-installments-table';
import { AccountStatementPaymentsTable } from '../components/account-statement-payments-table';
import { RefinancingNotice } from '../components/refinancing-notice';
import type { ApiAccountStatement } from '../types/api-account-statement';

export const AccountStatementPage: FC = () => {
    const { id } = useParams<{ id: string }>();
    const { company } = useCompany();
    const canPrint = usePermission('creditos', 'imprimir_estado_cuenta');

    const { data: estadoCuenta, error } = useResource<ApiAccountStatement>(`/creditos/${id}/estado-cuenta`);
    const { download, isDownloading, error: downloadError } = useDownloadFile();
    const [exportMessage, setExportMessage] = useState<string | null>(null);
    const [exportError, setExportError] = useState<string | null>(null);

    if (error) {
        return (
            <div className="rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                {error}
            </div>
        );
    }

    if (!estadoCuenta) {
        return (
            <div className="flex justify-center py-12">
                <Spinner size="lg" />
            </div>
        );
    }

    const { credito, saldo, refinanciacion, pagos, resumen_cuotas: resumenCuotas } = estadoCuenta;
    const cuotas = credito.cuotas ?? [];
    const cuotasPagadas = cuotas.filter((c) => c.estado === 'pagada').length;
    // Un pago reversado sigue listado para trazabilidad, pero no suma al total pagado.
    const totalPagado = pagos.filter((pago) => pago.reversa === null).reduce((total, pago) => total + parseFloat(pago.valor_pago), 0);

    return (
        <div className="flex min-h-full flex-col pb-6">
            <div className="mb-4 flex flex-wrap items-center gap-3 print:hidden">
                <PageBreadcrumb pageTitle="Estado de cuenta" />
                {canPrint && (
                    <>
                        <Button iconOnly
                            variant="outline"
                            startIcon={<Download className="size-4" />}
                            disabled={isDownloading}
                            onClick={() => download(`/creditos/${id}/estado-cuenta/pdf`, `estado-de-cuenta-credito-${id}.pdf`)}
                        >
                            {isDownloading ? 'Generando…' : 'Descargar PDF'}
                        </Button>
                        <Button iconOnly startIcon={<Printer className="size-4" />} onClick={() => window.print()}>Imprimir</Button>
                    </>
                )}
                <PermissionExportButton
                    modulo="creditos"
                    endpoint={`/creditos/${id}/estado-cuenta/exportar`}
                    filters={{}}
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

            {downloadError && (
                <div className="mb-4 print:hidden">
                    <Alert variant="danger" title="No se pudo descargar" message={downloadError} />
                </div>
            )}

            <div className="rounded-2xl border border-gray-200 bg-white p-6 md:p-8 dark:border-gray-800 dark:bg-white/3 print:border-0 print:p-0">
                <AccountStatementHeader company={company} credito={credito} cuotasPagadas={cuotasPagadas} fechaCorte={estadoCuenta.fecha_corte} />

                {refinanciacion && (
                    <RefinancingNotice vinculo={refinanciacion} valorPrestamo={credito.valor_prestamo} linkSuffix="/estado-cuenta" className="mb-6" />
                )}

                <AccountStatementPaymentsTable pagos={pagos} totalPagado={totalPagado} />

                <AccountStatementInstallmentsTable
                    cuotas={cuotas}
                    saldoCapitalTeoricoPorCuota={resumenCuotas.saldo_capital_teorico_por_cuota}
                    saldo={saldo}
                />

                <div className="mt-8 border-t border-gray-100 pt-4 text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
                    <p>
                        <strong>Observaciones:</strong> este documento es válido para pago total únicamente al día de generado.
                    </p>
                    <p>Cualquier inquietud, favor comunicarla por escrito a los canales de atención de la empresa.</p>
                </div>
            </div>
        </div>
    );
};
