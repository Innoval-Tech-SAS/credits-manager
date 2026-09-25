import type { FC, SubmitEvent } from 'react';
import { useState } from 'react';
import { useNavigate } from 'react-router';

import { useSendRequest } from '../../shared/hooks/use-send-request';
import { Button } from '@ui/button/components/button';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { Select } from '@ui/forms/components/select';
import { ToggleSwitchInput } from '@ui/forms/components/toggle-switch-input';
import { Spinner } from '@ui/spinner/components/spinner';
import { CLIENT_STATUS_BADGE_CLASSES } from '../constants/client-status-badge-classes';
import { NATURAL_DOCUMENT_TYPE_OPTIONS } from '../../third-parties/constants/natural-document-type-options';
import { EmployerSelector } from '../../employers/components/employer-selector';
import { ArrowRight } from 'lucide-react';
import type { ClientFormProps } from '../props/client-form-props';
import type { ApiClient } from '../types/api-client';
import type { ApiEmployer } from '../../employers/types/api-employer';
import type { NaturalDocumentType } from '../../third-parties/types/natural-document-type';

export const ClientForm: FC<ClientFormProps> = ({ clientId, initialData }) => {
    const navigate = useNavigate();
    const { sendRequest } = useSendRequest();

    const isEditMode = clientId !== undefined;

    const [nombres, setNombres] = useState(initialData?.tercero.nombres ?? '');
    const [apellidos, setApellidos] = useState(initialData?.tercero.apellidos ?? '');
    const [tipoDocumento, setTipoDocumento] = useState<NaturalDocumentType>(
        initialData?.tercero.tipo_documento === 'nit' ? 'cc' : (initialData?.tercero.tipo_documento ?? 'cc'),
    );
    const [numeroDocumento, setNumeroDocumento] = useState(initialData?.tercero.numero_documento ?? '');
    const [direccion, setDireccion] = useState(initialData?.tercero.direccion ?? '');
    const [barrio, setBarrio] = useState(initialData?.tercero.barrio ?? '');
    const [ciudad, setCiudad] = useState(initialData?.tercero.ciudad ?? '');
    const [celular, setCelular] = useState(initialData?.tercero.celular ?? '');
    const [telefonoFijo, setTelefonoFijo] = useState(initialData?.tercero.telefono_fijo ?? '');
    const [correo, setCorreo] = useState(initialData?.tercero.correo ?? '');
    const [empleador, setEmpleador] = useState<ApiEmployer | null>(initialData?.empleador ?? null);
    const [activo, setActivo] = useState<boolean>(initialData?.activo ?? true);
    const [error, setError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSubmit = async (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();
        setError(null);

        if (!empleador) {
            setError('Selecciona o crea un empleador.');
            return;
        }

        setIsSubmitting(true);

        const body = {
            nombres,
            apellidos,
            tipo_documento: tipoDocumento,
            numero_documento: numeroDocumento,
            direccion,
            barrio: barrio || null,
            ciudad,
            celular,
            telefono_fijo: telefonoFijo || null,
            correo,
            empleador_id: empleador.id,
            ...(isEditMode ? { activo } : {}),
        };

        try {
            let mensaje = isEditMode ? 'Cliente actualizado correctamente.' : 'Cliente creado correctamente.';

            if (isEditMode) {
                await sendRequest(`/clientes/${clientId}`, { method: 'PATCH', body });
            } else {
                const respuesta = await sendRequest<{ cliente: ApiClient }>('/clientes', { method: 'POST', body });

                if (respuesta.cliente.tercero_reutilizado) {
                    mensaje = `Cliente creado. Ya existía un registro con ese documento (${respuesta.cliente.tercero.nombre}); se usaron sus datos existentes.`;
                }
            }

            navigate('/clientes', { replace: true, state: { message: mensaje } });
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <form onSubmit={handleSubmit}>
            <div className="space-y-6">
                {error && (
                    <div className="rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                        {error}
                    </div>
                )}

                <div className={`grid grid-cols-1 gap-6 ${isEditMode ? 'md:grid-cols-3' : 'md:grid-cols-2'}`}>
                    <div>
                        <Label htmlFor="nombres">Nombres</Label>
                        <Input id="nombres" name="nombres" required value={nombres} onChange={(event) => setNombres(event.target.value)} />
                    </div>

                    <div>
                        <Label htmlFor="apellidos">Apellidos</Label>
                        <Input id="apellidos" name="apellidos" required value={apellidos} onChange={(event) => setApellidos(event.target.value)} />
                    </div>

                    {isEditMode && (
                        <div>
                            <Label htmlFor="activo">Estado</Label>
                            <div className="flex h-11 items-center gap-2.5">
                                <ToggleSwitchInput id="activo" checked={activo} onChange={setActivo} />
                                <span
                                    className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${CLIENT_STATUS_BADGE_CLASSES[activo ? 'activo' : 'inactivo']}`}
                                >
                                    {activo ? 'Activo' : 'Inactivo'}
                                </span>
                            </div>
                        </div>
                    )}
                </div>

                <div>
                    <p className="mb-3 text-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">Identificación</p>
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                        <div>
                            <Label htmlFor="tipo-documento">Tipo de documento</Label>
                            <Select
                                options={NATURAL_DOCUMENT_TYPE_OPTIONS}
                                defaultValue={tipoDocumento}
                                onChange={(value) => setTipoDocumento(value as NaturalDocumentType)}
                            />
                        </div>

                        <div>
                            <Label htmlFor="numero-documento">Número de documento</Label>
                            <Input
                                id="numero-documento"
                                name="numero-documento"
                                required
                                value={numeroDocumento}
                                onChange={(event) => setNumeroDocumento(event.target.value)}
                            />
                        </div>

                        <div>
                            <Label htmlFor="empleador">Empresa</Label>
                            <EmployerSelector id="empleador" selected={empleador} onSelect={setEmpleador} />
                        </div>
                    </div>
                </div>

                <div>
                    <p className="mb-3 text-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">Ubicación</p>
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                        <div>
                            <Label htmlFor="direccion">Dirección</Label>
                            <Input
                                id="direccion"
                                name="direccion"
                                required
                                value={direccion}
                                onChange={(event) => setDireccion(event.target.value)}
                            />
                        </div>

                        <div>
                            <Label htmlFor="barrio">Barrio</Label>
                            <Input id="barrio" name="barrio" value={barrio} onChange={(event) => setBarrio(event.target.value)} />
                        </div>

                        <div>
                            <Label htmlFor="ciudad">Ciudad</Label>
                            <Input
                                id="ciudad"
                                name="ciudad"
                                required
                                value={ciudad}
                                onChange={(event) => setCiudad(event.target.value)}
                            />
                        </div>
                    </div>
                </div>

                <div>
                    <p className="mb-3 text-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">Contacto</p>
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                        <div>
                            <Label htmlFor="celular">Celular</Label>
                            <Input
                                id="celular"
                                name="celular"
                                required
                                value={celular}
                                onChange={(event) => setCelular(event.target.value)}
                            />
                        </div>

                        <div>
                            <Label htmlFor="telefono-fijo">Teléfono fijo</Label>
                            <Input
                                id="telefono-fijo"
                                name="telefono-fijo"
                                value={telefonoFijo}
                                onChange={(event) => setTelefonoFijo(event.target.value)}
                            />
                        </div>

                        <div>
                            <Label htmlFor="correo">Correo</Label>
                            <Input
                                id="correo"
                                name="correo"
                                type="email"
                                required
                                value={correo}
                                onChange={(event) => setCorreo(event.target.value)}
                            />
                        </div>
                    </div>
                </div>

                <div className="flex justify-end border-t border-gray-100 pt-6 dark:border-gray-800">
                    <Button
                        type="submit"
                        disabled={isSubmitting}
                        startIcon={isSubmitting ? <Spinner size="sm" color="white" /> : undefined}
                        endIcon={isSubmitting ? undefined : <ArrowRight className="size-4" />}
                    >
                        {isSubmitting
                            ? isEditMode
                                ? 'Actualizando…'
                                : 'Creando…'
                            : isEditMode
                              ? 'Actualizar cliente'
                              : 'Crear cliente'}
                    </Button>
                </div>
            </div>
        </form>
    );
};
