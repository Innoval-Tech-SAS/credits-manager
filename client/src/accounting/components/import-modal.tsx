import type { FC } from 'react';
import { useState } from 'react';
import { Download, Upload } from 'lucide-react';

import { useSendRequest } from '../../shared/hooks/use-send-request';
import { downloadTextFile } from '../../shared/utils/download-text-file';
import { Button } from '@ui/button/components/button';
import { Dropzone } from '@ui/dropzone/components/dropzone';
import { Label } from '@ui/forms/components/label';
import { Modal } from '@ui/modal/components/modal';
import { Spinner } from '@ui/spinner/components/spinner';
import type { ImportModalProps } from '../props/import-modal-props';
import type { ApiOpeningBalanceImport } from '../types/api-opening-balance-import';

export const ImportModal: FC<ImportModalProps> = ({ onClose, tipo, titulo, columnas, notas, onImported }) => {
    const { sendRequest } = useSendRequest();

    const [archivo, setArchivo] = useState<File | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleDescargarPlantilla = () => {
        downloadTextFile(`plantilla-${tipo}.csv`, `${columnas.join(',')}\n`);
    };

    const handleImportar = async () => {
        if (!archivo) {
            setError('Selecciona un archivo CSV.');
            return;
        }

        setError(null);
        setIsSubmitting(true);

        try {
            const formData = new FormData();
            formData.set('tipo', tipo);
            formData.set('archivo', archivo);

            const respuesta = await sendRequest<{ migracion: ApiOpeningBalanceImport }>('/contabilidad/migraciones-apertura', {
                method: 'POST',
                body: formData,
            });

            onImported(respuesta.migracion);
            onClose();
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <Modal isOpen onClose={onClose} size="md">
            <h4 className="mb-4 text-lg font-semibold text-gray-800 dark:text-white/90">{titulo}</h4>

            {error && (
                <div className="mb-4 rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                    {error}
                </div>
            )}

            <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        Columnas requeridas: <span className="font-mono">{columnas.join(', ')}</span>
                    </p>
                    {notas && <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{notas}</p>}
                </div>
                <Button iconOnly variant="outline" size="sm" startIcon={<Download className="size-4" />} onClick={handleDescargarPlantilla}>
                    Descargar plantilla
                </Button>
            </div>

            <Label htmlFor="archivo">Archivo CSV</Label>
            <Dropzone
                onFileAccepted={setArchivo}
                hint={archivo ? archivo.name : 'Arrastra o selecciona un archivo .csv'}
                accept={{ 'text/csv': ['.csv'] }}
            />

            <div className="mt-6 flex justify-end gap-3">
                <Button variant="outline" onClick={onClose} disabled={isSubmitting}>
                    Cancelar
                </Button>
                <Button
                    onClick={() => void handleImportar()}
                    disabled={isSubmitting || !archivo}
                    startIcon={isSubmitting ? <Spinner size="sm" color="white" /> : <Upload className="size-4" />}
                >
                    {isSubmitting ? 'Importando…' : 'Importar'}
                </Button>
            </div>
        </Modal>
    );
};
