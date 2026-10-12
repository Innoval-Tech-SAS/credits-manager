import type { FC } from 'react';
import { useState } from 'react';
import { useNavigate } from 'react-router';

import { PermissionExportButton } from '../../shared/components/permission-export-button';
import { useResource } from '../../shared/hooks/use-resource';
import { Alert } from '@ui/alert/components/alert';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { PageSkeleton } from '@ui/skeleton/components/page-skeleton';
import { PortfolioCurrentTable } from '../components/portfolio-current-table';
import { PortfolioOverdueTable } from '../components/portfolio-overdue-table';
import { PortfolioSummaryCards } from '../components/portfolio-summary-cards';
import type { ApiPortfolioSummary } from '../types/api-portfolio-summary';

export const PortfolioPage: FC = () => {
    const navigate = useNavigate();
    const [exportMessage, setExportMessage] = useState<string | null>(null);
    const [exportError, setExportError] = useState<string | null>(null);

    const { data: resumen, error } = useResource<ApiPortfolioSummary>('/cartera');

    if (error) {
        return (
            <div className="rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                {error}
            </div>
        );
    }

    if (!resumen) {
        return <PageSkeleton />;
    }

    const { al_dia: alDia, en_mora: enMora, totales, envejecimiento_mora: envejecimientoMora } = resumen;

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Cartera" />

            {(exportMessage || exportError) && (
                <div className="mb-4 flex flex-col gap-4">
                    {exportMessage && <Alert variant="success" title="Listo" message={exportMessage} onDismiss={() => setExportMessage(null)} />}
                    {exportError && <Alert variant="danger" title="No se pudo exportar" message={exportError} onDismiss={() => setExportError(null)} />}
                </div>
            )}

            <PortfolioSummaryCards
                totales={totales}
                envejecimientoMora={envejecimientoMora}
                accion={
                    <PermissionExportButton
                        permiso="cartera.exportar_listado"
                        endpoint="/cartera/exportar"
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
                }
            />

            <PortfolioOverdueTable
                entradas={enMora}
                onView={(creditoId) => navigate(`/creditos/${creditoId}`)}
                onOpenEmployerReport={() => navigate('/cartera/reporte-empleador')}
            />

            <PortfolioCurrentTable entradas={alDia} onView={(creditoId) => navigate(`/creditos/${creditoId}`)} />
        </div>
    );
};
