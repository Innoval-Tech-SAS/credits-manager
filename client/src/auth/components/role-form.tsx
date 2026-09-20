import type { FC, SubmitEvent } from 'react';
import { useState } from 'react';
import { useNavigate } from 'react-router';

import { useResource } from '../../shared/hooks/use-resource';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import { Button } from '@ui/button/components/button';
import { Checkbox } from '@ui/forms/components/checkbox';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { Spinner } from '@ui/spinner/components/spinner';
import { ArrowRight } from 'lucide-react';
import type { RoleFormProps } from '../props/role-form-props';
import type { ApiPermisoCatalogItem } from '../types/api-permiso';
import { groupPermisosByModulo } from '../utils/group-permisos-by-modulo';

export const RoleForm: FC<RoleFormProps> = ({ roleId, initialData }) => {
    const navigate = useNavigate();
    const { sendRequest } = useSendRequest();
    const { data: permisosData } = useResource<{ permisos: ApiPermisoCatalogItem[] }>('/permisos');
    const permisos = permisosData?.permisos ?? [];

    const isEditMode = roleId !== undefined;
    const isRolAdmin = initialData?.nombre === 'admin';

    const [nombre, setNombre] = useState(initialData?.nombre ?? '');
    const [descripcion, setDescripcion] = useState(initialData?.descripcion ?? '');
    const [seleccionados, setSeleccionados] = useState<Set<number>>(
        () => new Set(initialData?.permisos.map((permiso) => permiso.id) ?? []),
    );
    const [error, setError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const togglePermiso = (permisoId: number, isChecked: boolean) => {
        setSeleccionados((current) => {
            const siguiente = new Set(current);
            if (isChecked) {
                siguiente.add(permisoId);
            } else {
                siguiente.delete(permisoId);
            }
            return siguiente;
        });
    };

    const handleSubmit = async (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();
        setError(null);
        setIsSubmitting(true);

        try {
            const body = { nombre, descripcion: descripcion || null, permisos: Array.from(seleccionados) };

            if (isEditMode) {
                await sendRequest(`/roles/${roleId}`, { method: 'PATCH', body });
            } else {
                await sendRequest('/roles', { method: 'POST', body });
            }

            navigate('/roles', {
                replace: true,
                state: { message: isEditMode ? 'Rol actualizado correctamente.' : 'Rol creado correctamente.' },
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

                <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div>
                        <Label htmlFor="nombre">Nombre</Label>
                        <Input
                            id="nombre"
                            name="nombre"
                            pill
                            disabled={isRolAdmin}
                            value={nombre}
                            onChange={(event) => setNombre(event.target.value)}
                        />
                        {isRolAdmin && (
                            <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">El rol de administrador no se puede renombrar.</p>
                        )}
                    </div>

                    <div>
                        <Label htmlFor="descripcion">Descripción</Label>
                        <Input
                            id="descripcion"
                            name="descripcion"
                            pill
                            value={descripcion}
                            onChange={(event) => setDescripcion(event.target.value)}
                        />
                    </div>
                </div>

                <div>
                    <h3 className="mb-4 text-xs font-semibold tracking-wide text-gray-400 uppercase dark:text-gray-500">
                        Permisos
                    </h3>
                    <div className="space-y-4">
                        {groupPermisosByModulo(permisos).map(([modulo, permisosDelModulo]) => (
                            <div key={modulo} className="rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/3">
                                <p className="mb-2 text-xs font-semibold text-gray-600 uppercase dark:text-gray-400">{modulo}</p>
                                <div className="flex flex-wrap gap-x-6 gap-y-2">
                                    {permisosDelModulo.map((permiso) => (
                                        <Checkbox
                                            key={permiso.id}
                                            id={`permiso-${permiso.id}`}
                                            label={permiso.accion}
                                            checked={seleccionados.has(permiso.id)}
                                            onChange={(isChecked) => togglePermiso(permiso.id, isChecked)}
                                        />
                                    ))}
                                </div>
                            </div>
                        ))}
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
                              ? 'Actualizar rol'
                              : 'Crear rol'}
                    </Button>
                </div>
            </div>
        </form>
    );
};
