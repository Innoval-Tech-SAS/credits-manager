import type { FC } from 'react';
import { useState } from 'react';

import { usePermission } from '../../auth/hooks/use-permission';
import { ExportButton } from '../../shared/components/export-button';
import { Alert } from '@ui/alert/components/alert';
import type { ReportExportButtonProps } from '../props/report-export-button-props';

/** Exportación de un reporte contable. Solo aparece con el permiso `contabilidad.exportar`. */
export const ReportExportButton: FC<ReportExportButtonProps> = ({ endpoint, filters, disabled = false }) => {
    const canExport = usePermission('informes_contables', 'exportar');
    const [message, setMessage] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);

    if (!canExport) {
        return null;
    }

    return (
        <div className="flex flex-col gap-4">
            <div className="flex justify-end">
                <ExportButton
                    endpoint={endpoint}
                    filters={filters}
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
