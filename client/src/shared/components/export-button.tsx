import type { FC } from 'react';
import { useState } from 'react';

import { Button } from '@ui/button/components/button';
import { Dropdown } from '@ui/dropdown/components/dropdown';
import { DropdownItem } from '@ui/dropdown/components/dropdown-item';
import { Download, FileSpreadsheet, FileText } from 'lucide-react';
import { useRequestExport } from '../hooks/use-request-export';
import type { ExportButtonProps } from '../props/export-button-props';
import type { ExportFormat } from '../types/export-format';

/**
 * Botón reutilizable de exportación: elige el formato y pide la exportación con los filtros vigentes.
 * Cada reporte solo pasa su `endpoint` y sus `filters`; no hay que modificar este componente.
 */
export const ExportButton: FC<ExportButtonProps> = ({ endpoint, filters, disabled = false, onRequested, onError }) => {
    const [isOpen, setIsOpen] = useState(false);
    const { requestExport, isRequesting } = useRequestExport();

    const handleSelect = async (format: ExportFormat) => {
        setIsOpen(false);

        try {
            onRequested?.(await requestExport(endpoint, format, filters));
        } catch (caught) {
            onError?.(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        }
    };

    return (
        <div className="relative">
            <Button
                variant="outline"
                className="dropdown-toggle w-full sm:w-auto"
                startIcon={<Download className="size-4" />}
                disabled={disabled || isRequesting}
                onClick={() => setIsOpen((previous) => !previous)}
            >
                {isRequesting ? 'Solicitando…' : 'Exportar'}
            </Button>

            <Dropdown isOpen={isOpen} onClose={() => setIsOpen(false)} className="w-48 p-2">
                <DropdownItem onItemClick={() => void handleSelect('xlsx')} className="flex items-center gap-2">
                    <FileSpreadsheet className="size-4" />
                    Excel (.xlsx)
                </DropdownItem>
                <DropdownItem onItemClick={() => void handleSelect('csv')} className="flex items-center gap-2">
                    <FileText className="size-4" />
                    CSV (.csv)
                </DropdownItem>
            </Dropdown>
        </div>
    );
};
