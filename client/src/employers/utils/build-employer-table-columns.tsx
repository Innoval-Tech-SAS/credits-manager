import type { DataTableColumn } from '@ui/table/props/data-table-props';
import { Pencil, Trash2 } from 'lucide-react';
import type { ApiEmployer } from '../types/api-employer';
import type { BuildEmployerTableColumnsOptions } from '../types/build-employer-table-columns-options';

export function buildEmployerTableColumns({
    canEdit,
    canDelete,
    onEdit,
    onDelete,
}: BuildEmployerTableColumnsOptions): DataTableColumn<ApiEmployer>[] {
    const columns: DataTableColumn<ApiEmployer>[] = [
        { key: 'nombre', label: 'Nombre', sortable: true },
        { key: 'nit', label: 'NIT', render: (row) => row.nit ?? '—' },
        { key: 'direccion', label: 'Dirección', render: (row) => row.direccion ?? '—' },
        { key: 'telefono', label: 'Teléfono', render: (row) => row.telefono ?? '—' },
    ];

    if (canEdit || canDelete) {
        columns.push({
            key: 'acciones',
            label: 'Acción',
            render: (row) => (
                <div className="flex w-full items-center gap-2">
                    {canDelete && (
                        <button
                            type="button"
                            onClick={() => onDelete(row)}
                            className="text-gray-500 hover:text-error-500 dark:text-gray-400 dark:hover:text-error-500"
                        >
                            <Trash2 className="size-5" />
                        </button>
                    )}
                    {canEdit && (
                        <button
                            type="button"
                            onClick={() => onEdit(row)}
                            className="text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white/90"
                        >
                            <Pencil className="size-5" />
                        </button>
                    )}
                </div>
            ),
        });
    }

    return columns;
}
