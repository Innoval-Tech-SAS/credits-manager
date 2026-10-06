import type { FC } from 'react';
import { useState } from 'react';

import { usePermission } from '../../auth/hooks/use-permission';
import { ExportButton } from '../../shared/components/export-button';
import { Alert } from '@ui/alert/components/alert';
import type { ReportExportButtonProps } from '../props/report-export-button-props';

/**
 * Exportación de un reporte contable. Excel y CSV requieren `informes_contables.exportar`; el PDF, `informes_contables.imprimir`.
 * Sin ninguno de los dos no se muestra el botón.
 */
export const ReportExportButton: FC<ReportExportButtonProps> = ({ endpoint, pdfEndpoint, pdfFilename, filters, disabled = false }) => {
    const canExport = usePermission('informes_contables', 'exportar');
    const canPrint = usePermission('informes_contables', 'imprimir');
    const [message, setMessage] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);

    if (!canExport && !canPrint) {
        return null;
    }

    return (
        <div className="flex flex-col gap-4">
            <div className="flex justify-end">
                <ExportButton
                    endpoint={endpoint}
                    filters={filters}
                    formats={canExport ? ['xlsx', 'csv'] : []}
                    pdfEndpoint={canPrint ? pdfEndpoint : undefined}
                    pdfFilename={pdfFilename}
                    disabled={disabled}
                    onRequested={() => {
                        setError(null);
                        setMessage('Exportación solicitada. Se genera en segundo plano y te avisaremos cuando esté lista para descargar.');
                    }}
                    onError={(errorMessage) => {
                        setMessage(null);
                        setError(errorMessage);
                    }}
                />
            </div>

            {message && <Alert variant="success" title="Listo" message={message} onDismiss={() => setMessage(null)} />}
            {error && <Alert variant="danger" title="No se pudo exportar" message={error} onDismiss={() => setError(null)} />}
        </div>
    );
};
