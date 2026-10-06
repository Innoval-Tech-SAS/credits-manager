import type { FC } from 'react';
import { FileDown } from 'lucide-react';

import { usePermission } from '../../auth/hooks/use-permission';
import { useDownloadFile } from '../../shared/hooks/use-download-file';
import type { ReceiptDownloadButtonProps } from '../props/receipt-download-button-props';

/** Número del recibo de caja del pago y descarga de su PDF. Un recibo anulado se marca, pero su PDF sigue disponible. */
export const ReceiptDownloadButton: FC<ReceiptDownloadButtonProps> = ({ payment }) => {
    const { download, isDownloading, error } = useDownloadFile();
    const canPrint = usePermission('pagos', 'imprimir_recibo');

    if (!payment.recibo) {
        return <span className="text-gray-400 dark:text-gray-600">—</span>;
    }

    const receiptNumber = payment.recibo.numero;
    const anulado = payment.recibo.estado === 'anulado';

    return (
        <div className="flex flex-wrap items-center gap-2">
            {canPrint ? (
                <button
                    type="button"
                    title={error ?? `Descargar el recibo ${receiptNumber} en PDF`}
                    disabled={isDownloading}
                    onClick={() => download(`/pagos/${payment.id}/recibo/pdf`, `recibo-de-caja-${receiptNumber}.pdf`)}
                    className={`inline-flex items-center gap-1.5 whitespace-nowrap hover:underline disabled:cursor-not-allowed disabled:opacity-50 ${
                        error ? 'text-error-600 dark:text-error-400' : 'text-brand-500 dark:text-brand-400'
                    } ${anulado ? 'line-through' : ''}`}
                >
                    <FileDown className="size-4" />
                    {isDownloading ? 'Generando…' : error ? 'Reintentar' : receiptNumber}
                </button>
            ) : (
                <span className={anulado ? 'line-through' : ''}>{receiptNumber}</span>
            )}
            {anulado && (
                <span className="inline-block rounded-full bg-error-50 px-2 py-0.5 text-xs font-medium whitespace-nowrap text-error-600 dark:bg-error-500/15 dark:text-error-500">
                    Anulado
                </span>
            )}
        </div>
    );
};
