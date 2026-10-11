import type { FC } from 'react';
import { useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router';
import { Pencil, Plus, Star, Trash2 } from 'lucide-react';

import { useResource } from '../../shared/hooks/use-resource';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import { useLocationSuccessMessage } from '../../shared/hooks/use-location-success-message';
import { Alert } from '@ui/alert/components/alert';
import { Button } from '@ui/button/components/button';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { ConfirmModal } from '@ui/modal/components/confirm-modal';
import { DataTable } from '@ui/table/components/data-table';
import { EmailTypeTabs } from '../components/email-type-tabs';
import type { ApiPlantillaCorreo } from '../types/api-plantilla-correo';
import type { EmailTemplatesListLocationState } from '../types/email-templates-list-location-state';
import type { EmailType } from '../types/email-type';

function esEmailType(valor: string | null): valor is EmailType {
    return valor === 'cobranza' || valor === 'facturacion';
}

export const EmailTemplatesPage: FC = () => {
    const navigate = useNavigate();
    const { sendRequest, isLoading } = useSendRequest();
    const [searchParams] = useSearchParams();

    const [tipo, setTipo] = useState<EmailType>(esEmailType(searchParams.get('tipo')) ? (searchParams.get('tipo') as EmailType) : 'cobranza');
    const [successMessage, setSuccessMessage] = useLocationSuccessMessage<EmailTemplatesListLocationState>();

    const { data, isLoading: isLoadingPlantillas, refetch } = useResource<{ plantillas: ApiPlantillaCorreo[] }>(`/correo/plantillas?tipo=${tipo}`);
    const plantillas = data?.plantillas ?? [];

    const [porEliminar, setPorEliminar] = useState<ApiPlantillaCorreo | null>(null);
    const [marcandoId, setMarcandoId] = useState<number | null>(null);
    const [error, setError] = useState<string | null>(null);

    const marcarPredeterminada = async (plantilla: ApiPlantillaCorreo) => {
        setError(null);
        setMarcandoId(plantilla.id);

        try {
            await sendRequest(`/correo/plantillas/${plantilla.id}/predeterminada`, { method: 'POST' });
            void refetch();
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setMarcandoId(null);
        }
    };

    const confirmarEliminar = async () => {
        if (porEliminar === null) {
            return;
        }

        setError(null);

        try {
            await sendRequest(`/correo/plantillas/${porEliminar.id}`, { method: 'DELETE' });
            setPorEliminar(null);
            void refetch();
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        }
    };

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Plantillas de correo" />

            <EmailTypeTabs tipo={tipo} onChange={setTipo} />

            {successMessage && (
                <div className="mb-6">
                    <Alert variant="success" title="Listo" message={successMessage} onDismiss={() => setSuccessMessage(null)} />
                </div>
            )}

            {error && (
                <div className="mb-6">
                    <Alert variant="danger" title="Error" message={error} onDismiss={() => setError(null)} />
                </div>
            )}

            <div className="flex-1 rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">Plantillas de {tipo === 'cobranza' ? 'cobranza' : 'facturación'}</h2>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {tipo === 'cobranza'
                                ? 'Mensajes de recordatorio que se envían a los clientes.'
                                : 'Mensajes de envío de facturas, notas crédito y notas débito.'}
                        </p>
                    </div>
                    <Button startIcon={<Plus className="size-4" />} onClick={() => navigate(`/plantillas-correo/${tipo}/crear`)}>
                        Nueva plantilla
                    </Button>
                </div>

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
                                        onClick={() => navigate(`/plantillas-correo/${tipo}/${plantilla.id}/editar`)}
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
                    loading={isLoadingPlantillas}
                    emptyMessage="Sin plantillas para este tipo."
                />
            </div>

            <ConfirmModal
                isOpen={porEliminar !== null}
                onClose={() => setPorEliminar(null)}
                onConfirm={() => void confirmarEliminar()}
                variant="danger"
                title="Eliminar plantilla"
                description={`La plantilla «${porEliminar?.nombre}» se eliminará. Esta acción no se puede deshacer.`}
                confirmLabel="Eliminar"
                isConfirming={isLoading}
            />
        </div>
    );
};
