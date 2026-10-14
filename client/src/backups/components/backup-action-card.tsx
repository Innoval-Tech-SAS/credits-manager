import type { FC } from 'react';
import { useState } from 'react';
import { CloudUpload } from 'lucide-react';

import { Alert } from '@ui/alert/components/alert';
import { Button } from '@ui/button/components/button';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import type { BackupActionCardProps } from '../props/backup-action-card-props';

/** Respaldo manual bajo demanda: la tarjeta de estado (`BackupsStatusCard`, compartida con Salud del sistema) no lleva acciones. */
export const BackupActionCard: FC<BackupActionCardProps> = ({ onCompleted }) => {
    const { sendRequest, isLoading } = useSendRequest();
    const [mensaje, setMensaje] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);

    const ejecutar = async () => {
        setMensaje(null);
        setError(null);

        try {
            const respuesta = await sendRequest<{ message: string }>('/respaldos/ejecutar', { method: 'POST' });
            setMensaje(respuesta.message);
            onCompleted();
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        }
    };

    return (
        <section className="flex flex-1 flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3">
            <div className="flex items-start gap-3">
                <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-500 dark:bg-brand-500/15 dark:text-brand-400">
                    <CloudUpload className="size-5" />
                </span>

                <div className="flex flex-col gap-1">
                    <h2 className="text-theme-xl font-semibold text-gray-800 dark:text-white/90">Respaldo manual</h2>
                    <p className="text-theme-sm text-gray-500 dark:text-gray-400">Crea una copia ahora mismo, sin esperar al ciclo automático.</p>
                </div>
            </div>

            {mensaje && <Alert variant="success" title="Listo" message={mensaje} onDismiss={() => setMensaje(null)} />}
            {error && <Alert variant="danger" title="No se pudo respaldar" message={error} onDismiss={() => setError(null)} />}

            <div className="flex">
                <Button className="w-full sm:w-auto" startIcon={<CloudUpload className="size-4" />} disabled={isLoading} onClick={() => void ejecutar()}>
                    {isLoading ? 'Respaldando… puede tardar varios minutos' : 'Respaldar ahora'}
                </Button>
            </div>
        </section>
    );
};
