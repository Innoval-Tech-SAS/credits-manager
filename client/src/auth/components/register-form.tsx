import type { FC, SubmitEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router';

import { useResource } from '../../shared/hooks/use-resource';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import { Button } from '@ui/button/components/button';
import { Checkbox } from '@ui/forms/components/checkbox';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { Select } from '@ui/forms/components/select';
import { ToggleSwitchInput } from '@ui/forms/components/toggle-switch-input';
import { Spinner } from '@ui/spinner/components/spinner';
import { KeyRound, User, Save } from 'lucide-react';
import type { RegisterFormProps } from '../props/register-form-props';
import type { ApiPermisoCatalogItem } from '../types/api-permiso';
import type { ApiRoleWithPermisos } from '../types/api-role';
import type { PaginatedResponse } from '../../shared/types/paginated-response';
import { groupPermisosByModulo } from '../utils/group-permisos-by-modulo';

const capitalize = (value: string) => value.charAt(0).toUpperCase() + value.slice(1);

function buildBaseline(rol: ApiRoleWithPermisos | undefined, permisos: ApiPermisoCatalogItem[]): Record<number, boolean> {
    const idsDelRol = new Set(rol?.permisos.map((permiso) => permiso.id) ?? []);
    return Object.fromEntries(permisos.map((permiso) => [permiso.id, idsDelRol.has(permiso.id)]));
}

export const RegisterForm: FC<RegisterFormProps> = ({ userId, initialData }) => {
    const navigate = useNavigate();
    const { sendRequest } = useSendRequest();
    const { data: rolesData } = useResource<PaginatedResponse<ApiRoleWithPermisos>>('/roles?per_page=100');
    const { data: permisosData } = useResource<{ permisos: ApiPermisoCatalogItem[] }>('/permisos');
    const roles = rolesData?.data ?? [];
    const permisos = permisosData?.permisos ?? [];

    const isEditMode = userId !== undefined;

    const [name, setName] = useState(initialData?.nombre ?? '');
    const [username, setUsername] = useState(initialData?.usuario ?? '');
    const [password, setPassword] = useState('');
    const [confirmPassword, setConfirmPassword] = useState('');
    const [roleId, setRoleId] = useState<number | null>(initialData?.rol?.id ?? null);
    const [overrides, setOverrides] = useState<Record<number, boolean>>({});
    const [active, setActive] = useState(initialData?.activo ?? true);
    const [isGlobal, setIsGlobal] = useState(initialData?.es_asesor_global ?? false);
    const [error, setError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const hasInitializedOverrides = useRef(false);
    const isAdvisorRole = roles.find((rol) => rol.id === roleId)?.nombre === 'asesor';
    const isAdvisorGlobal = isAdvisorRole && isGlobal;

    useEffect(() => {
        if (hasInitializedOverrides.current || roles.length === 0 || permisos.length === 0) {
            return;
        }

        hasInitializedOverrides.current = true;

        const rolSeleccionado = roles.find((rol) => rol.id === roleId);
        const base = buildBaseline(rolSeleccionado, permisos);

        if (initialData) {
            for (const excepcion of initialData.permisos_extra) {
                const permiso = permisos.find((item) => item.modulo === excepcion.modulo && item.accion === excepcion.accion);
                if (permiso) {
                    base[permiso.id] = excepcion.pivot.concedido;
                }
            }
        }

        setOverrides(base);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [roles, permisos]);

    const handleRoleChange = (value: string) => {
        const nuevoRolId = Number(value);
        setRoleId(nuevoRolId);
        setOverrides(buildBaseline(roles.find((rol) => rol.id === nuevoRolId), permisos));
    };

    const togglePermiso = (permisoId: number, isChecked: boolean) => {
        setOverrides((current) => ({ ...current, [permisoId]: isChecked }));
    };

    const roleOptions = roles.map((rol) => ({ value: String(rol.id), label: capitalize(rol.nombre) }));

    const handleSubmit = async (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();
        setError(null);

        if (!isEditMode && password !== confirmPassword) {
            setError('Las contraseñas no coinciden.');
            return;
        }

        if (!roleId) {
            setError('Selecciona un rol.');
            return;
        }

        setIsSubmitting(true);

        try {
            const baseline = buildBaseline(
                roles.find((rol) => rol.id === roleId),
                permisos,
            );

            const permisosExtra = permisos
                .filter((permiso) => (overrides[permiso.id] ?? false) !== (baseline[permiso.id] ?? false))
                .map((permiso) => ({ permiso_id: permiso.id, concedido: overrides[permiso.id] ?? false }));

            if (isEditMode) {
                await sendRequest(`/usuarios/${userId}`, {
                    method: 'PATCH',
                    body: { nombre: name, usuario: username, rol_id: roleId, activo: active, es_asesor_global: isAdvisorGlobal, permisos_extra: permisosExtra },
                });
            } else {
                await sendRequest('/usuarios', {
                    method: 'POST',
                    body: {
                        nombre: name,
                        usuario: username,
                        clave: password,
                        rol_id: roleId,
                        activo: active,
                        es_asesor_global: isAdvisorGlobal,
                        permisos_extra: permisosExtra,
                    },
                });
            }

            navigate('/usuarios', {
                replace: true,
                state: { message: isEditMode ? 'Usuario actualizado correctamente.' : 'Usuario creado correctamente.' },
            });
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <form onSubmit={handleSubmit}>
            <div className="space-y-8">
                {error && (
                    <div className="rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                        {error}
                    </div>
                )}

                <div className="flex flex-col gap-8 xl:grid xl:grid-cols-2 xl:items-start xl:gap-x-12">
                    <section>
                        <h3 className="mb-4 text-xs font-semibold tracking-wide text-gray-400 uppercase dark:text-gray-500">
                            Datos de la cuenta
                        </h3>
                        <div className="space-y-6">
                            <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                <div>
                                    <Label htmlFor="name">Nombre</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        pill
                                        startIcon={<User className="size-4" />}
                                        value={name}
                                        onChange={(event) => setName(event.target.value)}
                                    />
                                </div>

                                <div>
                                    <Label htmlFor="username">Usuario</Label>
                                    <Input
                                        id="username"
                                        name="username"
                                        pill
                                        startIcon={<User className="size-4" />}
                                        value={username}
                                        onChange={(event) => setUsername(event.target.value)}
                                    />
                                </div>
                            </div>

                            {!isEditMode && (
                                <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                    <div>
                                        <Label htmlFor="password">Contraseña</Label>
                                        <Input
                                            id="password"
                                            name="password"
                                            type="password"
                                            pill
                                            startIcon={<KeyRound className="size-4" />}
                                            minLength={8}
                                            hint="Mínimo 8 caracteres."
                                            value={password}
                                            onChange={(event) => setPassword(event.target.value)}
                                        />
                                    </div>

                                    <div>
                                        <Label htmlFor="confirm-password">Confirmar contraseña</Label>
                                        <Input
                                            id="confirm-password"
                                            name="confirm-password"
                                            type="password"
                                            pill
                                            startIcon={<KeyRound className="size-4" />}
                                            minLength={8}
                                            value={confirmPassword}
                                            onChange={(event) => setConfirmPassword(event.target.value)}
                                        />
                                    </div>
                                </div>
                            )}

                            <div>
                                <Label>Estado</Label>
                                <div className="flex h-11 items-center">
                                    <ToggleSwitchInput
                                        id="activo"
                                        label={active ? 'Activo' : 'Inactivo'}
                                        checked={active}
                                        onChange={setActive}
                                    />
                                </div>
                            </div>

                            <div className="md:w-1/2 md:pr-3">
                                <Label htmlFor="role">Rol</Label>
                                <Select
                                    options={roleOptions}
                                    defaultValue={roleId !== null ? String(roleId) : undefined}
                                    placeholder="Selecciona un rol…"
                                    onChange={handleRoleChange}
                                />
                                <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                    Los permisos base del usuario vienen de este rol.
                                </p>
                            </div>

                            {isAdvisorRole && (
                                <div>
                                    <Label>Alcance</Label>
                                    <div className="flex h-11 items-center">
                                        <ToggleSwitchInput
                                            id="es-global"
                                            label={isGlobal ? 'Ve todos los clientes' : 'Solo sus clientes asignados'}
                                            checked={isGlobal}
                                            onChange={setIsGlobal}
                                        />
                                    </div>
                                    <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                        Con el alcance global el asesor ve los clientes y créditos de toda la empresa; si no, solo los suyos.
                                    </p>
                                </div>
                            )}
                        </div>
                    </section>

                    <section className="border-t border-gray-100 pt-8 xl:border-t-0 xl:border-l xl:pt-0 xl:pl-12 dark:border-gray-800">
                        <h3 className="mb-4 text-xs font-semibold tracking-wide text-gray-400 uppercase dark:text-gray-500">
                            Permisos
                        </h3>
                        <p className="mb-3 text-xs text-gray-500 dark:text-gray-400">
                            Marcados los permisos que da el rol elegido. Puedes agregarle permisos extra o quitarle alguno puntual a
                            este usuario sin cambiar su rol.
                        </p>

                        <div className="space-y-4">
                            {groupPermisosByModulo(permisos).map(([modulo, permisosDelModulo]) => (
                                <div key={modulo} className="rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/3">
                                    <p className="mb-2 text-xs font-semibold text-gray-600 uppercase dark:text-gray-400">
                                        {modulo}
                                    </p>
                                    <div className="flex flex-wrap gap-x-6 gap-y-2">
                                        {permisosDelModulo.map((permiso) => (
                                            <Checkbox
                                                key={permiso.id}
                                                id={`permiso-${permiso.id}`}
                                                label={permiso.accion}
                                                checked={overrides[permiso.id] ?? false}
                                                onChange={(isChecked) => togglePermiso(permiso.id, isChecked)}
                                            />
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </section>
                </div>

                <div className="flex justify-end border-t border-gray-100 pt-6 dark:border-gray-800">
                    <Button
                        type="submit"
                        disabled={isSubmitting}
                        startIcon={isSubmitting ? <Spinner size="sm" color="white" /> : undefined}
                        endIcon={isSubmitting ? undefined : <Save className="size-4" />}
                    >
                        {isSubmitting
                            ? isEditMode
                                ? 'Actualizando…'
                                : 'Creando…'
                            : isEditMode
                              ? 'Actualizar usuario'
                              : 'Crear usuario'}
                    </Button>
                </div>
            </div>
        </form>
    );
};
