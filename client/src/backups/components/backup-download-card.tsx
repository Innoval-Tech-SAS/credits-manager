import type { FC } from 'react';

import { Button } from '@ui/button/components/button';
import { Alert } from '@ui/alert/components/alert';
import { Download } from 'lucide-react';
import { useDownloadFile } from '../../shared/hooks/use-download-file';
import type { BackupDownloadCardProps } from '../props/backup-download-card-props';

/** Tarjeta de una descarga de respaldo: descripción, botón y el aviso de error si falla. */
export const BackupDownloadCard: FC<BackupDownloadCardProps> = ({ title, description, buttonLabel, icon, endpoint, fallbackFilename }) => {
    const { download, isDownloading, error } = useDownloadFile();

    return (
        <section className="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3">
            <div className="flex items-start gap-3">
                <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-500 dark:bg-brand-500/15 dark:text-brand-400">
                    {icon}
                </span>

                <div className="flex flex-col gap-1">
                    <h2 className="text-theme-xl font-semibold text-gray-800 dark:text-white/90">{title}</h2>
                    <p className="text-theme-sm text-gray-500 dark:text-gray-400">{description}</p>
                </div>
            </div>

            {error && <Alert variant="danger" title="No se pudo descargar" message={error} />}

            <div className="flex">
                <Button
                    className="w-full sm:w-auto"
                    startIcon={<Download className="size-4" />}
                    disabled={isDownloading}
                    onClick={() => download(endpoint, fallbackFilename)}
                >
                    {isDownloading ? 'Preparando la descarga…' : buttonLabel}
                </Button>
            </div>
        </section>
    );
};
