import type { FC } from 'react';
import { ChevronDown } from 'lucide-react';

import { Checkbox } from '@ui/forms/components/checkbox';
import { PERMISSION_ORIGIN_BADGES } from '../constants/permission-origin-badges';
import type { PermissionModuleCardProps } from '../props/permission-module-card-props';

/**
 * Un módulo del panel de permisos: la casilla de la cabecera marca o desmarca de una vez todas sus acciones que el
 * usuario puede cambiar (queda en estado parcial si solo algunas están marcadas) y el contador resume cuántas tiene.
 */
export const PermissionModuleCard: FC<PermissionModuleCardProps> = ({
    modulo,
    visibles,
    isExpanded,
    onToggleExpanded,
    isChecked,
    canChange,
    onChange,
    getOrigin,
}) => {
    const marcados = modulo.permisos.filter(isChecked).length;
    const total = modulo.permisos.length;
    const todos = marcados === total;
    const cambiables = modulo.permisos.filter(canChange);
    const regionId = `permisos-modulo-${modulo.modulo}`;

    const toggleModulo = (checked: boolean) => {
        const ids = cambiables.filter((permiso) => isChecked(permiso) !== checked).map((permiso) => permiso.id);
        if (ids.length > 0) {
            onChange(ids, checked);
        }
    };

    return (
        <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/3">
            <div className="flex items-center gap-3 px-4 py-3">
                <Checkbox
                    id={`modulo-${modulo.modulo}`}
                    ariaLabel={`Todos los permisos de ${modulo.etiqueta}`}
                    checked={todos}
                    indeterminate={marcados > 0 && !todos}
                    disabled={cambiables.length === 0}
                    onChange={toggleModulo}
                />
                <button
                    type="button"
                    aria-expanded={isExpanded}
                    aria-controls={regionId}
                    onClick={onToggleExpanded}
                    className="flex min-w-0 flex-1 items-center justify-between gap-3 text-left"
                >
                    <span className="truncate text-sm font-medium text-gray-800 dark:text-white/90">{modulo.etiqueta}</span>
                    <span className="flex shrink-0 items-center gap-2">
                        <span
                            className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                marcados === 0
                                    ? 'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-400'
                                    : 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400'
                            }`}
                        >
                            {`${marcados}/${total}`}
                        </span>
                        <ChevronDown
                            className={`size-4 text-gray-500 transition-transform dark:text-gray-400 ${isExpanded ? 'rotate-180' : ''}`}
                        />
                    </span>
                </button>
            </div>

            {isExpanded && (
                <ul id={regionId} className="grid grid-cols-1 gap-3 border-t border-gray-100 px-4 py-4 sm:grid-cols-2 dark:border-gray-800">
                    {visibles.map((permiso) => {
                        const origen = getOrigin?.(permiso) ?? null;

                        return (
                            <li key={permiso.id} className="flex items-center justify-between gap-3">
                                <Checkbox
                                    id={`permiso-${permiso.id}`}
                                    label={permiso.accion_etiqueta}
                                    checked={isChecked(permiso)}
                                    disabled={!canChange(permiso)}
                                    onChange={(checked) => onChange([permiso.id], checked)}
                                />
                                {origen && (
                                    <span className={`shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium ${PERMISSION_ORIGIN_BADGES[origen].className}`}>
                                        {PERMISSION_ORIGIN_BADGES[origen].label}
                                    </span>
                                )}
                            </li>
                        );
                    })}
                </ul>
            )}
        </div>
    );
};
