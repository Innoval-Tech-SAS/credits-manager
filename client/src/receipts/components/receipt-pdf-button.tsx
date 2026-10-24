import type { FC } from 'react';
import { FileDown } from 'lucide-react';

import { usePermission } from '../../auth/hooks/use-permission';
import { useDownloadFile } from '../../shared/hooks/use-download-file';
import type { ReceiptPdfButtonProps } from '../props/receipt-pdf-button-props';

/** Número del recibo y descarga de su PDF. Un recibo anulado sigue descargable: su PDF muestra la anulación. */
export const ReceiptPdfButton: FC<ReceiptPdfButtonProps> = ({ pagoId, numero, anulado }) => {
    const { download, isDownloading, error } = useDownloadFile();
    const canPrint = usePermission('pagos', 'imprimir_recibo');

    if (!canPrint) {
        return <span className={anulado ? 'line-through' : ''}>{numero}</span>;
    }

    return (
        <button
            type="button"
            title={error ?? `Descargar el recibo ${numero} en PDF`}
            disabled={isDownloading}
            onClick={() => download(`/pagos/${pagoId}/recibo/pdf`, `recibo-de-caja-${numero}.pdf`)}
            className={`inline-flex items-center gap-1.5 whitespace-nowrap hover:underline disabled:cursor-not-allowed disabled:opacity-50 ${
                error ? 'text-error-600 dark:text-error-400' : 'text-brand-500 dark:text-brand-400'
            } ${anulado ? 'line-through' : ''}`}
        >
            <FileDown className="size-4" />
            {isDownloading ? 'Generando…' : error ? 'Reintentar' : numero}
        </button>
    );
};
