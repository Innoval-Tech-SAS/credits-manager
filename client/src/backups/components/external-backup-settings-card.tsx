import type { FC, SubmitEvent } from 'react';
import { useState } from 'react';
import { Cloud, PlugZap, Save } from 'lucide-react';

import { Alert } from '@ui/alert/components/alert';
import { Button } from '@ui/button/components/button';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { ToggleSwitchInput } from '@ui/forms/components/toggle-switch-input';
import { useResource } from '../../shared/hooks/use-resource';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import type { ApiExternalBackupConfig } from '../../monitoring/types/api-external-backup-config';
import type { ExternalBackupSettingsCardProps } from '../props/external-backup-settings-card-props';

type Draft = {
    activa: boolean;
    bucket: string;
    region: string;
    endpoint: string;
    usoPathStyle: boolean;
    claveAcceso: string;
    claveSecreta: string;
};

function borradorDesde(configuracion: ApiExternalBackupConfig): Draft {
    return {
        activa: configuracion.activa,
        bucket: configuracion.bucket ?? '',
        region: configuracion.region ?? '',
        endpoint: configuracion.endpoint ?? '',
        usoPathStyle: configuracion.uso_path_style,
        claveAcceso: configuracion.clave_acceso ?? '',
        claveSecreta: '',
    };
}

/** Copia externa opcional del respaldo a un disco S3: solo el superadmin la configura. */
export const ExternalBackupSettingsCard: FC<ExternalBackupSettingsCardProps> = ({ onSaved }) => {
    const { data, refetch } = useResource<{ configuracion: ApiExternalBackupConfig }>('/respaldos/externo');

    if (!data) {
        return null;
    }

    const handleSaved = () => {
        onSaved();
        refetch();
    };

    return <ExternalBackupSettingsForm configuracion={data.configuracion} onSaved={handleSaved} />;
};

const ExternalBackupSettingsForm: FC<{ configuracion: ApiExternalBackupConfig; onSaved: () => void }> = ({ configuracion, onSaved }) => {
    const { sendRequest, isLoading } = useSendRequest();
    const { sendRequest: enviarPrueba, isLoading: probando } = useSendRequest();

    const [draft, setDraft] = useState<Draft>(() => borradorDesde(configuracion));
    const [claveSecretaGuardada, setClaveSecretaGuardada] = useState(configuracion.clave_secreta_estado === 'configurada');
    const [error, setError] = useState<string | null>(null);
    const [success, setSuccess] = useState(false);
    const [resultadoPrueba, setResultadoPrueba] = useState<{ exitoso: boolean; mensaje: string } | null>(null);

    const actualizar = (cambios: Partial<Draft>) => {
        setDraft((anterior) => ({ ...anterior, ...cambios }));
        setSuccess(false);
        setResultadoPrueba(null);
    };

    const handleSubmit = async (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();
        setError(null);
        setSuccess(false);
        setResultadoPrueba(null);

        try {
            const respuesta = await sendRequest<{ configuracion: ApiExternalBackupConfig }>('/respaldos/externo', {
                method: 'PATCH',
                body: {
                    activa: draft.activa,
                    bucket: draft.bucket !== '' ? draft.bucket : null,
                    region: draft.region !== '' ? draft.region : null,
                    endpoint: draft.endpoint !== '' ? draft.endpoint : null,
                    uso_path_style: draft.usoPathStyle,
                    clave_acceso: draft.claveAcceso !== '' ? draft.claveAcceso : null,
                    clave_secreta: draft.claveSecreta !== '' ? draft.claveSecreta : null,
                },
            });

            setDraft(borradorDesde(respuesta.configuracion));
            setClaveSecretaGuardada(respuesta.configuracion.clave_secreta_estado === 'configurada');
            setSuccess(true);
            onSaved();
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        }
    };

    const probar = async () => {
        setResultadoPrueba(null);

        try {
            const respuesta = await enviarPrueba<{ exitoso: boolean; mensaje: string }>('/respaldos/externo/prueba', { method: 'POST' });
            setResultadoPrueba(respuesta);
        } catch (caught) {
            setResultadoPrueba({ exitoso: false, mensaje: caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.' });
        }
    };

    return (
        <section className="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3">
            <div className="flex items-start gap-3">
                <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-500 dark:bg-brand-500/15 dark:text-brand-400">
                    <Cloud className="size-5" />
                </span>

                <div className="flex flex-col gap-1">
                    <h2 className="text-theme-xl font-semibold text-gray-800 dark:text-white/90">Copia externa (S3)</h2>
                    <p className="text-theme-sm text-gray-500 dark:text-gray-400">
                        Sube una copia adicional del respaldo diario a un proveedor compatible con S3 (MinIO, DigitalOcean Spaces, Backblaze B2, AWS).
                    </p>
                </div>
            </div>

            {error && <Alert variant="danger" title="No se pudo guardar" message={error} onDismiss={() => setError(null)} />}
            {success && <Alert variant="success" title="Listo" message="Configuración guardada correctamente." onDismiss={() => setSuccess(false)} />}
            {resultadoPrueba && (
                <Alert
                    variant={resultadoPrueba.exitoso ? 'success' : 'danger'}
                    title={resultadoPrueba.exitoso ? 'Conexión correcta' : 'No se pudo conectar'}
                    message={resultadoPrueba.mensaje}
                    onDismiss={() => setResultadoPrueba(null)}
                />
            )}

            <form onSubmit={(event) => void handleSubmit(event)} className="flex flex-col gap-5" noValidate>
                <ToggleSwitchInput
                    id="activa"
                    label="Activar la copia externa"
                    checked={draft.activa}
                    onChange={(activa) => actualizar({ activa })}
                />

                <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <div>
                        <Label htmlFor="bucket">Bucket</Label>
                        <Input
                            id="bucket"
                            value={draft.bucket}
                            onChange={(event) => actualizar({ bucket: event.target.value })}
                        />
                    </div>

                    <div>
                        <Label htmlFor="region">Región</Label>
                        <Input
                            id="region"
                            value={draft.region}
                            onChange={(event) => actualizar({ region: event.target.value })}
                        />
                    </div>

                    <div className="md:col-span-2">
                        <Label htmlFor="endpoint">Endpoint (vacío = AWS S3 real)</Label>
                        <Input
                            id="endpoint"
                            placeholder="https://s3.proveedor.com"
                            value={draft.endpoint}
                            onChange={(event) => actualizar({ endpoint: event.target.value })}
                        />
                    </div>

                    <div>
                        <Label htmlFor="clave_acceso">Clave de acceso</Label>
                        <Input
                            id="clave_acceso"
                            autoComplete="off"
                            value={draft.claveAcceso}
                            onChange={(event) => actualizar({ claveAcceso: event.target.value })}
                        />
                    </div>

                    <div>
                        <Label htmlFor="clave_secreta">Clave secreta</Label>
                        <Input
                            id="clave_secreta"
                            type="password"
                            autoComplete="off"
                            placeholder={claveSecretaGuardada ? 'Guardada: déjalo en blanco para conservarla' : ''}
                            value={draft.claveSecreta}
                            onChange={(event) => actualizar({ claveSecreta: event.target.value })}
                        />
                    </div>
                </div>

                <ToggleSwitchInput
                    id="uso_path_style"
                    label='Usar rutas de estilo "path" (la mayoría de proveedores que no son AWS)'
                    checked={draft.usoPathStyle}
                    onChange={(usoPathStyle) => actualizar({ usoPathStyle })}
                />

                <div className="flex flex-col gap-3 border-t border-gray-200 pt-5 sm:flex-row dark:border-gray-800">
                    <Button type="submit" startIcon={<Save className="size-4" />} disabled={isLoading}>
                        {isLoading ? 'Guardando…' : 'Guardar'}
                    </Button>
                    <Button type="button" variant="outline" startIcon={<PlugZap className="size-4" />} disabled={probando} onClick={() => void probar()}>
                        {probando ? 'Probando…' : 'Probar conexión'}
                    </Button>
                </div>
            </form>
        </section>
    );
};
