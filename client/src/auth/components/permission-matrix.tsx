import type { FC } from 'react';
import { useState } from 'react';
import { ChevronsDownUp, ChevronsUpDown, Search } from 'lucide-react';

import { Button } from '@ui/button/components/button';
import { Input } from '@ui/forms/components/input';
import { Spinner } from '@ui/spinner/components/spinner';
import { PermissionModuleCard } from './permission-module-card';
import type { PermissionMatrixProps } from '../props/permission-matrix-props';
import { groupPermissionsByMenu } from '../utils/group-permissions-by-menu';
import { matchesPermissionSearch } from '../utils/matches-permission-search';

/**
 * Panel de permisos compartido por el formulario de rol y el de usuario: agrupa el catálogo por grupo del menú y
 * módulo, con nombres legibles, contador y casilla de "todos" por módulo. Mientras se busca, los módulos con
 * coincidencias se abren solos y solo muestran las acciones que coinciden.
 */
export const PermissionMatrix: FC<PermissionMatrixProps> = ({ permisos, isChecked, canChange, onChange, getOrigin, summary, isLoading = false }) => {
    const [busqueda, setBusqueda] = useState('');
    const [abiertos, setAbiertos] = useState<Set<string>>(() => new Set());

    const grupos = groupPermissionsByMenu(permisos);
    const marcados = permisos.filter(isChecked).length;
    const buscando = busqueda.trim() !== '';
    const todosAbiertos = grupos.every((grupo) => grupo.modulos.every((modulo) => abiertos.has(modulo.modulo)));

    const toggleModulo = (modulo: string) => {
        setAbiertos((actuales) => {
            const siguientes = new Set(actuales);
            if (siguientes.has(modulo)) {
                siguientes.delete(modulo);
            } else {
                siguientes.add(modulo);
            }
            return siguientes;
        });
    };

    const toggleTodos = () => {
        setAbiertos(todosAbiertos ? new Set() : new Set(grupos.flatMap((grupo) => grupo.modulos.map((modulo) => modulo.modulo))));
    };

    return (
        <section className="space-y-5">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h3 className="text-base font-semibold text-gray-800 dark:text-white/90">Permisos</h3>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{`${marcados} de ${permisos.length} permisos marcados`}</p>
                </div>
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div className="w-full sm:w-64">
                        <Input
                            placeholder="Buscar permiso…"
                            startIcon={<Search className="size-4" />}
                            value={busqueda}
                            onChange={(event) => setBusqueda(event.target.value)}
                        />
                    </div>
                    {!buscando && (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            startIcon={todosAbiertos ? <ChevronsDownUp className="size-4" /> : <ChevronsUpDown className="size-4" />}
                            onClick={toggleTodos}
                        >
                            {todosAbiertos ? 'Contraer todo' : 'Expandir todo'}
                        </Button>
                    )}
                </div>
            </div>

            {summary}

            {isLoading ? (
                <div className="flex justify-center py-8">
                    <Spinner />
                </div>
            ) : (
                <div className="space-y-6">
                    {grupos.map((grupo) => {
                        const modulos = grupo.modulos
                            .map((modulo) => ({ modulo, visibles: modulo.permisos.filter((permiso) => matchesPermissionSearch(permiso, busqueda)) }))
                            .filter(({ visibles }) => visibles.length > 0);

                        if (modulos.length === 0) {
                            return null;
                        }

                        return (
                            <div key={grupo.grupo} className="space-y-3">
                                <h4 className="text-xs font-semibold tracking-wide text-gray-400 uppercase dark:text-gray-500">{grupo.etiqueta}</h4>
                                {modulos.map(({ modulo, visibles }) => (
                                    <PermissionModuleCard
                                        key={modulo.modulo}
                                        modulo={modulo}
                                        visibles={visibles}
                                        isExpanded={buscando || abiertos.has(modulo.modulo)}
                                        onToggleExpanded={() => toggleModulo(modulo.modulo)}
                                        isChecked={isChecked}
                                        canChange={canChange}
                                        onChange={onChange}
                                        getOrigin={getOrigin}
                                    />
                                ))}
                            </div>
                        );
                    })}

                    {buscando && grupos.every((grupo) => grupo.modulos.every((modulo) => !modulo.permisos.some((permiso) => matchesPermissionSearch(permiso, busqueda)))) && (
                        <p className="py-6 text-center text-sm text-gray-500 dark:text-gray-400">Ningún permiso coincide con la búsqueda.</p>
                    )}
                </div>
            )}
        </section>
    );
};
