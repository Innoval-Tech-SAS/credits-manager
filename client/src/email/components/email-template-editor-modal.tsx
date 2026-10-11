import type { FC } from 'react';
import { useRef, useState } from 'react';
import { Eye } from 'lucide-react';

import { useSendRequest } from '../../shared/hooks/use-send-request';
import { Button } from '@ui/button/components/button';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { Modal } from '@ui/modal/components/modal';
import { EMAIL_TEMPLATE_VARIABLES } from '../constants/email-template-variables';
import { EmailPreviewModal } from './email-preview-modal';
import { EmailVariablePicker } from './email-variable-picker';
import type { EmailTemplateEditorModalProps } from '../props/email-template-editor-modal-props';
import type { ApiEmailPreview } from '../types/api-email-preview';
import type { ApiPlantillaCorreo } from '../types/api-plantilla-correo';

type CampoActivo = {
    campo: 'asunto' | 'cuerpo';
    elemento: HTMLInputElement | HTMLTextAreaElement;
};

/** Crea o edita una plantilla de un tipo (nombre, asunto y cuerpo HTML). El tipo es fijo: no viaja en este formulario al editar. */
export const EmailTemplateEditorModal: FC<EmailTemplateEditorModalProps> = ({ tipo, plantilla, onClose, onSaved }) => (
    <Modal isOpen onClose={onClose} size="lg">
        <FormularioPlantilla tipo={tipo} plantilla={plantilla} onClose={onClose} onSaved={onSaved} />
    </Modal>
);

const FormularioPlantilla: FC<EmailTemplateEditorModalProps> = ({ tipo, plantilla, onClose, onSaved }) => {
    const { sendRequest, isLoading } = useSendRequest();
    const esEdicion = plantilla !== null;

    const [nombre, setNombre] = useState(plantilla?.nombre ?? '');
    const [asunto, setAsunto] = useState(plantilla?.asunto ?? '');
    const [cuerpo, setCuerpo] = useState(plantilla?.cuerpo ?? '');
    const [error, setError] = useState<string | null>(null);

    const [preview, setPreview] = useState<ApiEmailPreview | null>(null);
    const [previewError, setPreviewError] = useState<string | null>(null);
    const [isPreviewing, setIsPreviewing] = useState(false);

    const campoActivo = useRef<CampoActivo | null>(null);

    const insertarVariable = (clave: string) => {
        const token = `{${clave}}`;
        const campo = campoActivo.current?.campo ?? 'cuerpo';
        const elemento = campoActivo.current?.elemento ?? null;
        const valorActual = campo === 'asunto' ? asunto : cuerpo;
        const inicio = elemento?.selectionStart ?? valorActual.length;
        const fin = elemento?.selectionEnd ?? inicio;
        const siguiente = valorActual.slice(0, inicio) + token + valorActual.slice(fin);

        if (campo === 'asunto') {
            setAsunto(siguiente);
        } else {
            setCuerpo(siguiente);
        }

        const posicion = inicio + token.length;

        requestAnimationFrame(() => {
            if (elemento?.isConnected) {
                elemento.focus();
                elemento.setSelectionRange(posicion, posicion);
            }
        });
    };

    const handlePreview = async () => {
        setPreviewError(null);
        setIsPreviewing(true);

        try {
            const respuesta = await sendRequest<{ vista_previa: ApiEmailPreview }>('/correo/plantillas/vista-previa', {
                method: 'POST',
                body: { tipo, asunto, cuerpo },
            });
            setPreview(respuesta.vista_previa);
        } catch (caught) {
            setPreviewError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setIsPreviewing(false);
        }
    };

    const handleSubmit = async () => {
        setError(null);

        try {
            if (esEdicion) {
                await sendRequest<{ plantilla: ApiPlantillaCorreo }>(`/correo/plantillas/${plantilla.id}`, {
                    method: 'PUT',
                    body: { nombre, asunto, cuerpo },
                });
            } else {
                await sendRequest<{ plantilla: ApiPlantillaCorreo }>('/correo/plantillas', {
                    method: 'POST',
                    body: { tipo, nombre, asunto, cuerpo },
                });
            }

            onSaved();
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        }
    };

    const formularioValido = nombre.trim() !== '' && asunto.trim() !== '' && cuerpo.trim() !== '';

    return (
        <div className="flex flex-col gap-6">
            <div className="pr-10">
                <h3 className="text-lg font-semibold text-gray-800 dark:text-white/90">{esEdicion ? 'Editar plantilla' : 'Nueva plantilla'}</h3>
                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {tipo === 'cobranza' ? 'Plantilla de recordatorio de cobranza.' : 'Plantilla de facturación (factura, nota crédito o nota débito).'}
                </p>
            </div>

            {error && (
                <div className="rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                    {error}
                </div>
            )}
            {previewError && (
                <div className="rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                    {previewError}
                </div>
            )}

            <div className="flex flex-col gap-2">
                <Label htmlFor="plantilla-nombre">Nombre de la plantilla</Label>
                <Input id="plantilla-nombre" name="nombre" required maxLength={120} value={nombre} onChange={(event) => setNombre(event.target.value)} />
            </div>

            <div className="flex min-w-0 flex-col gap-6">
                <div className="flex flex-col gap-2">
                    <Label htmlFor="plantilla-asunto">Asunto</Label>
                    <Input
                        id="plantilla-asunto"
                        name="asunto"
                        required
                        maxLength={200}
                        value={asunto}
                        onFocus={(event) => {
                            campoActivo.current = { campo: 'asunto', elemento: event.currentTarget };
                        }}
                        onChange={(event) => setAsunto(event.target.value)}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="plantilla-cuerpo">Cuerpo del correo (HTML)</Label>
                    <textarea
                        id="plantilla-cuerpo"
                        name="cuerpo"
                        rows={12}
                        required
                        value={cuerpo}
                        onFocus={(event) => {
                            campoActivo.current = { campo: 'cuerpo', elemento: event.currentTarget };
                        }}
                        onChange={(event) => setCuerpo(event.target.value)}
                        className="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 font-mono text-sm text-gray-800 focus:border-brand-300 focus:outline-hidden dark:border-gray-700 dark:text-white/90 dark:focus:border-brand-800"
                    />
                </div>
            </div>

            <EmailVariablePicker variables={EMAIL_TEMPLATE_VARIABLES[tipo]} onPick={insertarVariable} />

            <div className="flex flex-col-reverse gap-3 border-t border-gray-200 pt-6 sm:flex-row sm:justify-between dark:border-gray-800">
                <Button type="button" variant="outline" startIcon={<Eye className="size-4" />} disabled={isPreviewing} onClick={() => void handlePreview()}>
                    {isPreviewing ? 'Generando...' : 'Ver vista previa'}
                </Button>

                <div className="flex flex-col-reverse gap-3 sm:flex-row">
                    <Button type="button" variant="outline" onClick={onClose} disabled={isLoading}>
                        Cancelar
                    </Button>
                    <Button type="button" disabled={isLoading || !formularioValido} onClick={() => void handleSubmit()}>
                        {isLoading ? 'Guardando...' : 'Guardar plantilla'}
                    </Button>
                </div>
            </div>

            {preview && <EmailPreviewModal preview={preview} onClose={() => setPreview(null)} />}
        </div>
    );
};
