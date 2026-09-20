import type { FC, SubmitEvent } from 'react';
import { useState } from 'react';
import { useNavigate } from 'react-router';

import { EmployerSelector } from '../../employers/components/employer-selector';
import type { ApiEconomicActivity } from '../../expenses/types/api-economic-activity';
import { useResource } from '../../shared/hooks/use-resource';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import { Button } from '@ui/button/components/button';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { Select } from '@ui/forms/components/select';
import { ToggleSwitchInput } from '@ui/forms/components/toggle-switch-input';
import { Spinner } from '@ui/spinner/components/spinner';
import { ArrowRight } from 'lucide-react';
import { THIRD_PARTY_DOCUMENT_TYPE_OPTIONS } from '../constants/third-party-document-type-options';
import { THIRD_PARTY_ROLE_OPTIONS } from '../constants/third-party-role-options';
import type { ApiClient } from '../../clients/types/api-client';
import type { ApiEmployer } from '../../employers/types/api-employer';
import type { ApiSupplier } from '../types/api-supplier';
import type { ThirdPartyFormProps } from '../props/third-party-form-props';
import type { ApiThirdParty } from '../types/api-third-party';
import type { ThirdPartyRole } from '../types/third-party-role';

const TIPO_PERSONA_OPTIONS = [
    { value: 'natural', label: 'Persona natural' },
    { value: 'juridica', label: 'Persona jurídica' },
];

export const ThirdPartyForm: FC<ThirdPartyFormProps> = ({ thirdPartyId, initialData }) => {
    const navigate = useNavigate();
    const { sendRequest } = useSendRequest();

    const isEditMode = thirdPartyId !== undefined;

    const [rol, setRol] = useState<ThirdPartyRole>('otro');
    const [empleador, setEmpleador] = useState<ApiEmployer | null>(null);

    const [tipoDocumento, setTipoDocumento] = useState(initialData?.tipo_documento ?? 'cc');
    const [numeroDocumento, setNumeroDocumento] = useState(initialData?.numero_documento ?? '');
    const [nombre, setNombre] = useState(initialData?.nombre ?? '');
    const [direccion, setDireccion] = useState(initialData?.direccion ?? '');
    const [barrio, setBarrio] = useState(initialData?.barrio ?? '');
    const [ciudad, setCiudad] = useState(initialData?.ciudad ?? '');
    const [celular, setCelular] = useState(initialData?.celular ?? '');
    const [telefonoFijo, setTelefonoFijo] = useState(initialData?.telefono_fijo ?? '');
    const [correo, setCorreo] = useState(initialData?.correo ?? '');
    const [regimenTributario, setRegimenTributario] = useState(initialData?.regimen_tributario ?? '');
    const [tipoPersona, setTipoPersona] = useState(initialData?.tipo_persona ?? '');
    const [autorretenedor, setAutorretenedor] = useState<boolean>(initialData?.autorretenedor ?? false);
    const [responsableIva, setResponsableIva] = useState<boolean>(initialData?.responsable_iva ?? false);
    const [actividadEconomicaId, setActividadEconomicaId] = useState(
        initialData?.actividad_economica_id ? String(initialData.actividad_economica_id) : '',
    );
    const [activo, setActivo] = useState<boolean>(initialData?.activo ?? true);
    const [error, setError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const { data: actividadesData } = useResource<{ actividades_economicas: ApiEconomicActivity[] }>('/gastos/actividades-economicas');
    const actividadOptions = (actividadesData?.actividades_economicas ?? []).map((actividad) => ({
        value: String(actividad.id),
        label: `${actividad.codigo} - ${actividad.nombre}`,
    }));

    const requiereDatosDeCliente = !isEditMode && rol === 'cliente';

    const handleRolChange = (value: string) => {
        setRol(value as ThirdPartyRole);
        setEmpleador(null);
    };

    const handleSubmit = async (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();
        setError(null);

        if (requiereDatosDeCliente && !empleador) {
            setError('Selecciona o crea un empleador.');
            return;
        }

        setIsSubmitting(true);

        const datosIdentidad = {
            tipo_documento: tipoDocumento,
            numero_documento: numeroDocumento,
            nombre,
            direccion: direccion || null,
            barrio: barrio || null,
            ciudad: ciudad || null,
            celular: celular || null,
            telefono_fijo: telefonoFijo || null,
            correo: correo || null,
            regimen_tributario: regimenTributario || null,
            tipo_persona: tipoPersona || null,
            autorretenedor,
            responsable_iva: responsableIva,
            actividad_economica_id: actividadEconomicaId ? Number(actividadEconomicaId) : null,
        };

        try {
            let mensaje = isEditMode ? 'Tercero actualizado correctamente.' : 'Tercero creado correctamente.';

            if (isEditMode) {
                await sendRequest(`/terceros/${thirdPartyId}`, { method: 'PATCH', body: { ...datosIdentidad, activo } });
            } else if (rol === 'cliente') {
                const respuesta = await sendRequest<{ cliente: ApiClient }>('/clientes', {
                    method: 'POST',
                    body: { ...datosIdentidad, empleador_id: empleador!.id },
                });

                mensaje = respuesta.cliente.tercero_reutilizado
                    ? `Cliente creado. Ya existía un tercero con ese documento (${respuesta.cliente.tercero.nombre}); se usaron sus datos existentes.`
                    : 'Cliente creado correctamente.';
            } else if (rol === 'proveedor') {
                const respuesta = await sendRequest<{ proveedor: ApiSupplier }>('/proveedores', {
                    method: 'POST',
                    body: datosIdentidad,
                });

                mensaje = respuesta.proveedor.tercero_reutilizado
                    ? `Proveedor creado. Ya existía un tercero con ese documento (${respuesta.proveedor.tercero.nombre}); se usaron sus datos existentes.`
                    : 'Proveedor creado correctamente.';
            } else {
                const respuesta = await sendRequest<{ tercero: ApiThirdParty }>('/terceros', { method: 'POST', body: datosIdentidad });

                mensaje = respuesta.tercero.tercero_reutilizado
                    ? `Tercero creado. Ya existía un registro con ese documento (${respuesta.tercero.nombre}); se usaron sus datos existentes.`
                    : 'Tercero creado correctamente.';
            }

            navigate('/terceros', { replace: true, state: { message: mensaje } });
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

                <div className="flex flex-col gap-4 sm:flex-row sm:justify-between">
                    <div className="flex-1">
                        <Label htmlFor="nombre">Nombre</Label>
                        <Input id="nombre" name="nombre" required value={nombre} onChange={(event) => setNombre(event.target.value)} />
                    </div>

                    {!isEditMode && (
                        <div className="sm:w-48">
                            <Label htmlFor="rol">Tipo</Label>
                            <Select options={THIRD_PARTY_ROLE_OPTIONS} defaultValue={rol} onChange={handleRolChange} />
                        </div>
                    )}

                    {isEditMode && (
                        <div className="sm:w-36">
                            <Label htmlFor="activo">Estado</Label>
                            <div className="flex h-11 items-center gap-2.5">
                                <ToggleSwitchInput id="activo" checked={activo} onChange={setActivo} />
                                <span className="text-sm text-gray-600 dark:text-gray-300">{activo ? 'Activo' : 'Inactivo'}</span>
                            </div>
                        </div>
                    )}
                </div>

                <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div>
                        <Label htmlFor="tipo-documento">Tipo de documento</Label>
                        <Select
                            options={THIRD_PARTY_DOCUMENT_TYPE_OPTIONS}
                            defaultValue={tipoDocumento}
                            onChange={setTipoDocumento}
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
                </div>

                {requiereDatosDeCliente && (
                    <div className="md:w-1/2 md:pr-3">
                        <Label htmlFor="empleador">Empleador</Label>
                        <EmployerSelector id="empleador" selected={empleador} onSelect={setEmpleador} />
                    </div>
                )}

                <div>
                    <p className="mb-3 text-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">Ubicación</p>
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                        <div>
                            <Label htmlFor="direccion">Dirección</Label>
                            <Input
                                id="direccion"
                                name="direccion"
                                required={requiereDatosDeCliente}
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
                                required={requiereDatosDeCliente}
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
                                required={requiereDatosDeCliente}
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
                                required={requiereDatosDeCliente}
                                value={correo}
                                onChange={(event) => setCorreo(event.target.value)}
                            />
                        </div>
                    </div>
                </div>

                <div>
                    <p className="mb-3 text-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">Datos fiscales</p>
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                        <div>
                            <Label htmlFor="regimen-tributario">Régimen tributario</Label>
                            <Input
                                id="regimen-tributario"
                                name="regimen-tributario"
                                value={regimenTributario}
                                onChange={(event) => setRegimenTributario(event.target.value)}
                            />
                        </div>

                        <div>
                            <Label htmlFor="tipo-persona">Tipo de persona</Label>
                            <Select
                                options={TIPO_PERSONA_OPTIONS}
                                placeholder="--"
                                defaultValue={tipoPersona ?? ''}
                                onChange={setTipoPersona}
                            />
                        </div>

                        <div>
                            <Label htmlFor="actividad-economica">Actividad económica</Label>
                            <Select
                                options={actividadOptions}
                                placeholder="--"
                                defaultValue={actividadEconomicaId}
                                onChange={setActividadEconomicaId}
                            />
                        </div>
                    </div>

                    <div className="mt-4 flex flex-wrap gap-8">
                        <ToggleSwitchInput
                            id="autorretenedor"
                            label="Autorretenedor"
                            checked={autorretenedor}
                            onChange={setAutorretenedor}
                        />
                        <ToggleSwitchInput
                            id="responsable-iva"
                            label="Responsable de IVA"
                            checked={responsableIva}
                            onChange={setResponsableIva}
                        />
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
                                ? 'Actualizar tercero'
                                : 'Crear tercero'}
                    </Button>
                </div>
            </div>
        </form>
    );
};
