import type { DataTableColumn } from '@ui/table/props/data-table-props';
import { Eye, Pencil, Trash2 } from 'lucide-react';
import { CLIENT_STATUS_BADGE_CLASSES } from '../constants/client-status-badge-classes';
import type { ApiClient } from '../types/api-client';
import type { BuildClientTableColumnsOptions } from '../types/build-client-table-columns-options';

export function buildClientTableColumns({
    canView,
    canEdit,
    canDelete,
    onEdit,
    onView,
    onDelete,
}: BuildClientTableColumnsOptions): DataTableColumn<ApiClient>[] {
    const columns: DataTableColumn<ApiClient>[] = [
        { key: 'nombre', label: 'Nombre', sortable: true, render: (row) => row.tercero.nombre },
        {
            key: 'numero_documento',
            label: 'Documento',
            sortable: true,
            render: (row) => `${row.tercero.tipo_documento.toUpperCase()} ${row.tercero.numero_documento}`,
        },
        { key: 'celular', label: 'Celular', render: (row) => row.tercero.celular ?? '—' },
        { key: 'empleador', label: 'Empresa', sortable: true, render: (row) => row.empleador?.nombre ?? '—' },
        {
            key: 'activo',
            label: 'Estado',
            render: (row) => (
                <span
                    className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${CLIENT_STATUS_BADGE_CLASSES[row.activo ? 'activo' : 'inactivo']}`}
                >
                    {row.activo ? 'Activo' : 'Inactivo'}
                </span>
            ),
        },
        {
            key: 'acciones',
            label: 'Acción',
            render: (row) => (
                <div className="flex w-full items-center gap-3">
                    {canView && (
                        <button
                            type="button"
                            onClick={() => onView(row)}
                            className="text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white/90"
                        >
                            <Eye className="size-5" />
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

                    {canDelete && (
                        <button
                            type="button"
                            onClick={() => onDelete(row)}
                            className="text-gray-500 hover:text-error-500 dark:text-gray-400 dark:hover:text-error-500"
                        >
                            <Trash2 className="size-5" />
                        </button>
                    )}
                </div>
            ),
        },
    ];

    return columns;
}
