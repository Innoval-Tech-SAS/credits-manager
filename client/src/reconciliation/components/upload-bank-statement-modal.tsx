import type { FC } from 'react';
import { useState } from 'react';

import { useSendRequest } from '../../shared/hooks/use-send-request';
import { ACCEPTED_SPREADSHEET_TYPES } from '../../shared/constants/accepted-spreadsheet-types';
import { Button } from '@ui/button/components/button';
import { DatePicker } from '@ui/forms/components/date-picker';
import { Dropzone } from '@ui/dropzone/components/dropzone';
import { Label } from '@ui/forms/components/label';
import { Modal } from '@ui/modal/components/modal';
import type { UploadBankStatementModalProps } from '../props/upload-bank-statement-modal-props';
import type { ApiBankStatement } from '../types/api-bank-statement';

const hoyBogota = () => new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Bogota' }).format(new Date());

export const UploadBankStatementModal: FC<UploadBankStatementModalProps> = ({ isOpen, cuentaBancariaId, onClose, onUploaded, onError }) => (
    <Modal isOpen={isOpen} onClose={onClose} size="sm">
        {isOpen && <FormularioCarga cuentaBancariaId={cuentaBancariaId} onClose={onClose} onUploaded={onUploaded} onError={onError} />}
    </Modal>
);

type FormularioCargaProps = Omit<UploadBankStatementModalProps, 'isOpen'>;

/** El archivo no trae año en sus fechas (solo "d/mm"): basta el mes de su primera fila para reconstruirlas todas. */
const FormularioCarga: FC<FormularioCargaProps> = ({ cuentaBancariaId, onClose, onUploaded, onError }) => {
    const { sendRequest, isLoading } = useSendRequest();

    const [archivo, setArchivo] = useState<File | null>(null);
    const [fechaReferencia, setFechaReferencia] = useState(hoyBogota());

    const formularioValido = archivo !== null && fechaReferencia !== '';

    const cargar = async () => {
        if (!archivo) {
            return;
        }

        try {
            const formData = new FormData();
            formData.append('archivo', archivo);
            formData.append('mes_primera_fila', fechaReferencia.slice(0, 7));

            const respuesta = await sendRequest<{ extracto: ApiBankStatement }>(`/conciliacion/cuentas-bancarias/${cuentaBancariaId}/extractos`, {
                method: 'POST',
                body: formData,
            });

            onUploaded(`Extracto en carga (#${respuesta.extracto.id}). Se validará en segundo plano.`);
        } catch (caught) {
            onError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        }
    };

    return (
        <div className="flex flex-col gap-5">
            <div className="flex flex-col gap-1 pr-10">
                <h3 className="text-lg font-semibold text-gray-800 dark:text-white/90">Cargar extracto bancario</h3>
                <p className="text-sm text-gray-500 dark:text-gray-400">Archivo Excel (.xlsx) del banco, en su formato original.</p>
            </div>

            <div>
                <Label htmlFor="extracto-mes-primera-fila">Mes de la primera fila del archivo</Label>
                <DatePicker id="extracto-mes-primera-fila" value={fechaReferencia} onChange={setFechaReferencia} required />
            </div>

            <div>
                <Label htmlFor="extracto-archivo">Archivo</Label>
                {archivo ? (
                    <div className="flex items-center justify-between rounded-xl border border-gray-200 px-4 py-3 text-sm dark:border-gray-800">
                        <span className="text-gray-700 dark:text-gray-300">{archivo.name}</span>
                        <button type="button" className="text-error-600 hover:underline dark:text-error-400" onClick={() => setArchivo(null)}>
                            Quitar
                        </button>
                    </div>
                ) : (
                    <Dropzone
                        accept={ACCEPTED_SPREADSHEET_TYPES}
                        label="Arrastra y suelta el extracto aquí"
                        hint="Excel (.xlsx) del banco"
                        onFileAccepted={setArchivo}
                    />
                )}
            </div>

            <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Button variant="outline" onClick={onClose} disabled={isLoading}>
                    Cancelar
                </Button>
                <Button disabled={isLoading || !formularioValido} onClick={() => void cargar()}>
                    {isLoading ? 'Cargando…' : 'Cargar extracto'}
                </Button>
            </div>
        </div>
    );
};
