import type { DataTableColumn } from '@ui/table/props/data-table-props';
import { Pencil, Trash2 } from 'lucide-react';
import type { ApiRoleWithPermisos } from '../types/api-role';
import type { BuildRoleTableColumnsOptions } from '../types/build-role-table-columns-options';

export function buildRoleTableColumns({ onEdit, onDelete }: BuildRoleTableColumnsOptions): DataTableColumn<ApiRoleWithPermisos>[] {
    return [
        {
            key: 'nombre',
            label: 'Nombre',
            render: (row) => (
                <span
                    className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${
                        row.nombre === 'admin'
                            ? 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400'
                            : 'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-400'
                    }`}
                >
                    {row.nombre.charAt(0).toUpperCase() + row.nombre.slice(1)}
                </span>
            ),
        },
        { key: 'descripcion', label: 'Descripción', render: (row) => row.descripcion ?? '—' },
        { key: 'permisos', label: 'Permisos', render: (row) => `${row.permisos.length} permiso${row.permisos.length === 1 ? '' : 's'}` },
        {
            key: 'acciones',
            label: 'Acción',
            render: (row) => (
                <div className="flex w-full items-center gap-2">
                    {row.nombre !== 'admin' && (
                        <button
                            type="button"
                            onClick={() => onDelete(row)}
                            className="text-gray-500 hover:text-error-500 dark:text-gray-400 dark:hover:text-error-500"
                        >
                            <Trash2 className="size-5" />
                        </button>
                    )}
                    <button
                        type="button"
                        onClick={() => onEdit(row)}
                        className="text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white/90"
                    >
                        <Pencil className="size-5" />
                    </button>
                </div>
            ),
        },
    ];
}
