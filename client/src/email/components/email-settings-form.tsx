import type { FC, SubmitEvent } from 'react';
import { useState } from 'react';

import { useSendRequest } from '../../shared/hooks/use-send-request';
import { Button } from '@ui/button/components/button';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { Select } from '@ui/forms/components/select';
import { CIFRADO_OPTIONS, DRIVER_OPTIONS } from '../constants/email-options';
import { HtmlPreview } from './html-preview';
import type { EmailSettingsFormProps } from '../props/email-settings-form-props';
import type { ApiEmailPreview, ApiEmailTestResult } from '../types/api-email-preview';
import type { ApiEmailSettings } from '../types/api-email-settings';

const CAMPO_TEXTO =
    'w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:border-brand-300 focus:outline-hidden dark:border-gray-700 dark:text-white/90 dark:focus:border-brand-800';

/** Edita la configuración SMTP y la plantilla HTML. La clave nunca llega del servidor: vacía significa "conservar". */
export const EmailSettingsForm: FC<EmailSettingsFormProps> = ({ settings, onSaved }) => {
    const { sendRequest } = useSendRequest();

    const [driver, setDriver] = useState(settings.driver);
    const [remitente, setRemitente] = useState(settings.remitente ?? '');
    const [nombreRemitente, setNombreRemitente] = useState(settings.nombre_remitente ?? '');
    const [host, setHost] = useState(settings.host ?? '');
    const [puerto, setPuerto] = useState(settings.puerto === null ? '' : String(settings.puerto));
    const [cifrado, setCifrado] = useState(settings.cifrado);
    const [usuario, setUsuario] = useState(settings.usuario ?? '');
    const [clave, setClave] = useState('');
    const [asunto, setAsunto] = useState(settings.plantilla.asunto);
    const [cuerpo, setCuerpo] = useState(settings.plantilla.cuerpo);

    const [error, setError] = useState<string | null>(null);
    const [success, setSuccess] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const [preview, setPreview] = useState<ApiEmailPreview | null>(null);
    const [previewError, setPreviewError] = useState<string | null>(null);
    const [isPreviewing, setIsPreviewing] = useState(false);

    const [destinatarioPrueba, setDestinatarioPrueba] = useState('');
    const [resultadoPrueba, setResultadoPrueba] = useState<ApiEmailTestResult | null>(null);
    const [errorPrueba, setErrorPrueba] = useState<string | null>(null);
    const [isTesting, setIsTesting] = useState(false);

    const esSmtp = driver === 'smtp';

    const handleSubmit = async (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();
        setError(null);
        setSuccess(false);
        setIsSubmitting(true);

        try {
            await sendRequest('/correo/configuracion', {
                method: 'PUT',
                body: {
                    remitente,
                    nombre_remitente: nombreRemitente,
                    driver,
                    host: esSmtp ? host : null,
                    puerto: esSmtp && puerto !== '' ? Number(puerto) : null,
                    cifrado,
                    usuario: usuario === '' ? null : usuario,
                    clave: clave === '' ? null : clave,
                    plantilla_asunto: asunto,
                    plantilla_cuerpo: cuerpo,
                },
            });

            setClave('');
            setSuccess(true);
            onSaved();
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setIsSubmitting(false);
        }
    };

    const handlePreview = async () => {
        setPreviewError(null);
        setIsPreviewing(true);

        try {
            const respuesta = await sendRequest<{ vista_previa: ApiEmailPreview }>('/correo/vista-previa', {
                method: 'POST',
                body: { asunto, cuerpo },
            });
            setPreview(respuesta.vista_previa);
        } catch (caught) {
            setPreviewError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setIsPreviewing(false);
        }
    };

    const handleTest = async () => {
        setErrorPrueba(null);
        setResultadoPrueba(null);
        setIsTesting(true);

        try {
            setResultadoPrueba(
                await sendRequest<ApiEmailTestResult>('/correo/configuracion/prueba', {
                    method: 'POST',
                    body: { destinatario: destinatarioPrueba },
                }),
            );
        } catch (caught) {
            setErrorPrueba(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setIsTesting(false);
        }
    };

    return (
        <div className="flex flex-col gap-8">
            <form onSubmit={handleSubmit} className="flex flex-col gap-6">
                {error && (
                    <div className="rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                        {error}
                    </div>
                )}
                {success && (
                    <div className="rounded-lg border border-success-500 bg-success-50 px-4 py-3 text-sm text-success-600 dark:border-success-800 dark:bg-success-500/10 dark:text-success-400">
                        Configuración guardada correctamente.
                    </div>
                )}

                <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div>
                        <Label htmlFor="remitente">Correo remitente</Label>
                        <Input id="remitente" name="remitente" type="email" required value={remitente} onChange={(event) => setRemitente(event.target.value)} />
                    </div>
                    <div>
                        <Label htmlFor="nombre-remitente">Nombre del remitente</Label>
                        <Input id="nombre-remitente" name="nombre-remitente" required value={nombreRemitente} onChange={(event) => setNombreRemitente(event.target.value)} />
                    </div>
                    <div>
                        <Label htmlFor="driver">Proveedor</Label>
                        <Select options={DRIVER_OPTIONS} defaultValue={settings.driver} onChange={(valor) => setDriver(valor as ApiEmailSettings['driver'])} />
                    </div>
                    {esSmtp && (
                        <div>
                            <Label htmlFor="cifrado">Cifrado</Label>
                            <Select options={CIFRADO_OPTIONS} defaultValue={settings.cifrado} onChange={(valor) => setCifrado(valor as ApiEmailSettings['cifrado'])} />
                        </div>
                    )}
                    {esSmtp && (
                        <>
                            <div>
                                <Label htmlFor="host">Servidor (host)</Label>
                                <Input id="host" name="host" required value={host} onChange={(event) => setHost(event.target.value)} />
                            </div>
                            <div>
                                <Label htmlFor="puerto">Puerto</Label>
                                <Input id="puerto" name="puerto" type="number" min="1" max="65535" required value={puerto} onChange={(event) => setPuerto(event.target.value)} />
                            </div>
                            <div>
                                <Label htmlFor="usuario">Usuario</Label>
                                <Input id="usuario" name="usuario" autoComplete="off" value={usuario} onChange={(event) => setUsuario(event.target.value)} />
                            </div>
                            <div>
                                <Label htmlFor="clave">Clave</Label>
                                <Input
                                    id="clave"
                                    name="clave"
                                    type="password"
                                    autoComplete="new-password"
                                    placeholder={settings.clave_estado === 'configurada' ? 'Configurada: escriba una nueva para cambiarla' : 'Sin configurar'}
                                    value={clave}
                                    onChange={(event) => setClave(event.target.value)}
                                />
                            </div>
                        </>
                    )}
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="asunto">Asunto del recordatorio</Label>
                    <Input id="asunto" name="asunto" required value={asunto} onChange={(event) => setAsunto(event.target.value)} />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="cuerpo">Cuerpo del recordatorio (HTML)</Label>
                    <textarea id="cuerpo" name="cuerpo" rows={10} required value={cuerpo} onChange={(event) => setCuerpo(event.target.value)} className={`${CAMPO_TEXTO} font-mono`} />
                    <div className="flex flex-wrap gap-2 text-xs text-gray-500 dark:text-gray-400">
                        Variables:
                        {settings.variables.map((variable) => (
                            <code key={variable.clave} title={variable.descripcion} className="rounded bg-gray-100 px-1.5 py-0.5 text-gray-700 dark:bg-white/5 dark:text-gray-300">
                                {`{${variable.clave}}`}
                            </code>
                        ))}
                    </div>
                </div>

                <div className="flex flex-col gap-3 sm:flex-row sm:justify-end">
                    <Button type="button" variant="outline" disabled={isPreviewing} onClick={() => void handlePreview()}>
                        {isPreviewing ? 'Generando...' : 'Vista previa'}
                    </Button>
                    <Button type="submit" disabled={isSubmitting}>
                        {isSubmitting ? 'Guardando...' : 'Guardar configuración'}
                    </Button>
                </div>
            </form>

            {previewError && (
                <div className="rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                    {previewError}
                </div>
            )}

            {preview && (
                <section className="flex flex-col gap-3 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3">
                    <div>
                        <p className="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">Asunto</p>
                        <p className="text-sm text-gray-800 dark:text-white/90">{preview.asunto}</p>
                    </div>
                    <HtmlPreview html={preview.cuerpo} title="Vista previa del correo" />
                    <p className="text-xs text-gray-500 dark:text-gray-400">Datos de ejemplo. El correo real usa los datos de cada cliente.</p>
                </section>
            )}

            <section className="flex flex-col gap-4 border-t border-gray-200 pt-6 dark:border-gray-800">
                <div>
                    <h2 className="text-base font-semibold text-gray-800 dark:text-white/90">Probar la configuración</h2>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">Envía un correo de prueba con la configuración guardada. No muestra credenciales.</p>
                </div>
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div className="flex-1">
                        <Label htmlFor="destinatario-prueba">Destinatario</Label>
                        <Input id="destinatario-prueba" name="destinatario-prueba" type="email" value={destinatarioPrueba} onChange={(event) => setDestinatarioPrueba(event.target.value)} />
                    </div>
                    <Button type="button" variant="outline" disabled={isTesting || destinatarioPrueba === ''} onClick={() => void handleTest()}>
                        {isTesting ? 'Enviando...' : 'Enviar prueba'}
                    </Button>
                </div>
                {errorPrueba && (
                    <div className="rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                        {errorPrueba}
                    </div>
                )}
                {resultadoPrueba && (
                    <div
                        className={
                            resultadoPrueba.enviado
                                ? 'rounded-lg border border-success-500 bg-success-50 px-4 py-3 text-sm text-success-600 dark:border-success-800 dark:bg-success-500/10 dark:text-success-400'
                                : 'rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400'
                        }
                    >
                        {resultadoPrueba.mensaje}
                    </div>
                )}
            </section>
        </div>
    );
};
