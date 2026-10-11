import type { FC } from 'react';
import { useState } from 'react';
import { Pencil, Plus, Star, Trash2 } from 'lucide-react';

import { useSendRequest } from '../../shared/hooks/use-send-request';
import { Button } from '@ui/button/components/button';
import { ConfirmModal } from '@ui/modal/components/confirm-modal';
import { DataTable } from '@ui/table/components/data-table';
import { EmailTemplateEditorModal } from './email-template-editor-modal';
import type { EmailTemplateListProps } from '../props/email-template-list-props';
import type { ApiPlantillaCorreo } from '../types/api-plantilla-correo';

/** Plantillas de un tipo (cobranza o facturación): crear, editar, marcar predeterminada y eliminar. */
export const EmailTemplateList: FC<EmailTemplateListProps> = ({ tipo, plantillas, onChanged }) => {
    const { sendRequest, isLoading } = useSendRequest();

    const [editorAbierto, setEditorAbierto] = useState<'nueva' | ApiPlantillaCorreo | null>(null);
    const [porEliminar, setPorEliminar] = useState<ApiPlantillaCorreo | null>(null);
    const [marcandoId, setMarcandoId] = useState<number | null>(null);
    const [error, setError] = useState<string | null>(null);

    const marcarPredeterminada = async (plantilla: ApiPlantillaCorreo) => {
        setError(null);
        setMarcandoId(plantilla.id);

        try {
            await sendRequest(`/correo/plantillas/${plantilla.id}/predeterminada`, { method: 'POST' });
            onChanged();
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setMarcandoId(null);
        }
    };

    const eliminar = async () => {
        if (porEliminar === null) {
            return;
        }

        setError(null);

        try {
            await sendRequest(`/correo/plantillas/${porEliminar.id}`, { method: 'DELETE' });
            setPorEliminar(null);
            onChanged();
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        }
    };

    return (
        <section className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 className="text-base font-semibold text-gray-800 dark:text-white/90">Plantillas</h2>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {tipo === 'cobranza' ? 'Mensajes de recordatorio de cobranza.' : 'Mensajes de envío de facturas, notas crédito y notas débito.'}
                    </p>
                </div>
                <Button size="sm" startIcon={<Plus className="size-4" />} onClick={() => setEditorAbierto('nueva')}>
                    Nueva plantilla
                </Button>
            </div>

            {error && (
                <div className="rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                    {error}
                </div>
            )}

            <DataTable<ApiPlantillaCorreo>
                columns={[
                    {
                        key: 'nombre',
                        label: 'Nombre',
                        render: (plantilla) => (
                            <div className="flex items-center gap-2">
                                <span className="text-gray-800 dark:text-white/90">{plantilla.nombre}</span>
                                {plantilla.es_predeterminada && (
                                    <span className="inline-flex items-center gap-1 rounded-full bg-success-50 px-2.5 py-0.5 text-xs font-medium text-success-600 dark:bg-success-500/15 dark:text-success-500">
                                        <Star className="size-3" />
                                        Predeterminada
                                    </span>
                                )}
                            </div>
                        ),
                    },
                    { key: 'asunto', label: 'Asunto' },
                    {
                        key: 'acciones',
                        label: 'Acciones',
                        render: (plantilla) => (
                            <div className="flex items-center gap-3">
                                <button
                                    type="button"
                                    onClick={() => setEditorAbierto(plantilla)}
                                    className="inline-flex items-center gap-1.5 text-sm font-medium text-brand-500 hover:text-brand-600 dark:text-brand-400"
                                >
                                    <Pencil className="size-4" />
                                    Editar
                                </button>
                                {!plantilla.es_predeterminada && (
                                    <button
                                        type="button"
                                        disabled={marcandoId !== null}
                                        onClick={() => void marcarPredeterminada(plantilla)}
                                        className="inline-flex items-center gap-1.5 text-sm font-medium text-gray-600 hover:text-gray-800 disabled:opacity-50 dark:text-gray-400 dark:hover:text-white/90"
                                    >
                                        <Star className="size-4" />
                                        {marcandoId === plantilla.id ? 'Marcando...' : 'Marcar predeterminada'}
                                    </button>
                                )}
                                <button
                                    type="button"
                                    onClick={() => setPorEliminar(plantilla)}
                                    className="inline-flex items-center gap-1.5 text-sm font-medium text-error-600 hover:text-error-700 dark:text-error-400"
                                >
                                    <Trash2 className="size-4" />
                                    Eliminar
                                </button>
                            </div>
                        ),
                    },
                ]}
                rows={plantillas}
                getRowKey={(plantilla) => plantilla.id}
                emptyMessage="Sin plantillas para este tipo."
            />

            {editorAbierto && (
                <EmailTemplateEditorModal
                    tipo={tipo}
                    plantilla={editorAbierto === 'nueva' ? null : editorAbierto}
                    onClose={() => setEditorAbierto(null)}
                    onSaved={() => {
                        setEditorAbierto(null);
                        onChanged();
                    }}
                />
            )}

            <ConfirmModal
                isOpen={porEliminar !== null}
                onClose={() => setPorEliminar(null)}
                onConfirm={() => void eliminar()}
                variant="danger"
                title="Eliminar plantilla"
                description={`La plantilla «${porEliminar?.nombre}» se eliminará. Esta acción no se puede deshacer.`}
                confirmLabel="Eliminar"
                isConfirming={isLoading}
            />
        </section>
    );
};
