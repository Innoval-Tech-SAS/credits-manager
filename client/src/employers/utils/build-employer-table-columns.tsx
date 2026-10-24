import type { DataTableColumn } from '@ui/table/props/data-table-props';
import { Pencil, Trash2 } from 'lucide-react';
import { ActionButton } from '@ui/action-button/components/action-button';
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
                        <ActionButton icon={<Trash2 className="size-5" />} label="Eliminar" variant="danger" onClick={() => onDelete(row)} />
                    )}
                    {canEdit && <ActionButton icon={<Pencil className="size-5" />} label="Editar" onClick={() => onEdit(row)} />}
                </div>
            ),
        });
    }

    return columns;
}
