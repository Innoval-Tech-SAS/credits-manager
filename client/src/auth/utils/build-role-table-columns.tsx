import type { DataTableColumn } from '@ui/table/props/data-table-props';
import { Pencil, Trash2 } from 'lucide-react';
import { ActionButton } from '@ui/action-button/components/action-button';
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
                        <ActionButton icon={<Trash2 className="size-5" />} label="Eliminar" variant="danger" onClick={() => onDelete(row)} />
                    )}
                    <ActionButton icon={<Pencil className="size-5" />} label="Editar" onClick={() => onEdit(row)} />
                </div>
            ),
        },
    ];
}
