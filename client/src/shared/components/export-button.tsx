import type { FC } from 'react';
import { useState } from 'react';

import { Button } from '@ui/button/components/button';
import { Dropdown } from '@ui/dropdown/components/dropdown';
import { DropdownItem } from '@ui/dropdown/components/dropdown-item';
import { Download, FileSpreadsheet, FileText } from 'lucide-react';
import { useDownloadFile } from '../hooks/use-download-file';
import { useRequestExport } from '../hooks/use-request-export';
import type { ExportButtonProps } from '../props/export-button-props';
import type { ExportFormat } from '../types/export-format';

const DEFAULT_FORMATS: ExportFormat[] = ['xlsx', 'csv'];

/**
 * Botón reutilizable de exportación: elige el formato y pide la exportación con los filtros vigentes.
 * Cada reporte solo pasa su `endpoint` y sus `filters`; no hay que modificar este componente.
 * Con `pdfEndpoint` suma la opción PDF, que se descarga al momento en lugar de pedirse en segundo plano.
 */
export const ExportButton: FC<ExportButtonProps> = ({
    endpoint,
    filters,
    formats = DEFAULT_FORMATS,
    pdfEndpoint,
    pdfFilename = 'documento.pdf',
    align = 'right',
    disabled = false,
    onRequested,
    onError,
}) => {
    const [isOpen, setIsOpen] = useState(false);
    const { requestExport, isRequesting } = useRequestExport();
    const { download, isDownloading } = useDownloadFile();

    const handleSelect = async (format: ExportFormat) => {
        setIsOpen(false);

        try {
            onRequested?.(await requestExport(endpoint, format, filters));
        } catch (caught) {
            onError?.(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        }
    };

    const handleSelectPdf = async () => {
        setIsOpen(false);

        const activeFilters = Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== ''));
        const message = await download(`${pdfEndpoint}?${new URLSearchParams(activeFilters).toString()}`, pdfFilename);

        if (message) {
            onError?.(message);
        }
    };

    return (
        <div className="relative">
            <Button
                variant="outline"
                className="dropdown-toggle w-full sm:w-auto"
                startIcon={<Download className="size-4" />}
                disabled={disabled || isRequesting || isDownloading}
                onClick={() => setIsOpen((previous) => !previous)}
            >
                {isRequesting ? 'Solicitando…' : isDownloading ? 'Generando…' : 'Exportar'}
            </Button>

            <Dropdown isOpen={isOpen} onClose={() => setIsOpen(false)} className={`w-48 p-2 ${align === 'left' ? 'left-0' : ''}`}>
                {formats.includes('xlsx') && (
                    <DropdownItem onItemClick={() => void handleSelect('xlsx')} className="flex items-center gap-2">
                        <FileSpreadsheet className="size-4" />
                        Excel (.xlsx)
                    </DropdownItem>
                )}
                {formats.includes('csv') && (
                    <DropdownItem onItemClick={() => void handleSelect('csv')} className="flex items-center gap-2">
                        <FileText className="size-4" />
                        CSV (.csv)
                    </DropdownItem>
                )}
                {pdfEndpoint && (
                    <DropdownItem onItemClick={() => void handleSelectPdf()} className="flex items-center gap-2">
                        <FileText className="size-4" />
                        PDF (.pdf)
                    </DropdownItem>
                )}
            </Dropdown>
        </div>
    );
};
