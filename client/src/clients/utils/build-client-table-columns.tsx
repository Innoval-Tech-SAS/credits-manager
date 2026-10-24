import type { DataTableColumn } from '@ui/table/props/data-table-props';
import { Eye, Pencil, Trash2 } from 'lucide-react';
import { ActionButton } from '@ui/action-button/components/action-button';
import { CLIENT_STATUS_BADGE_CLASSES } from '../constants/client-status-badge-classes';
import { WhatsAppButton } from '../components/whatsapp-button';
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
        {
            key: 'celular',
            label: 'Celular',
            render: (row) => (
                <span className="inline-flex items-center gap-2">
                    {row.tercero.celular ?? '—'}
                    {row.tercero.celular && <WhatsAppButton variant="icono" nombre={row.tercero.nombre} celular={row.tercero.celular} />}
                </span>
            ),
        },
        { key: 'empleador', label: 'Empresa', sortable: true, render: (row) => row.empleador?.nombre ?? '—' },
        { key: 'asesor', label: 'Asesor', render: (row) => row.asesor?.nombre ?? '—' },
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
                    {canView && <ActionButton icon={<Eye className="size-5" />} label="Ver" onClick={() => onView(row)} />}

                    <WhatsAppButton variant="icono" nombre={row.tercero.nombre} celular={row.tercero.celular} />

                    {canEdit && <ActionButton icon={<Pencil className="size-5" />} label="Editar" onClick={() => onEdit(row)} />}

                    {canDelete && (
                        <ActionButton icon={<Trash2 className="size-5" />} label="Eliminar" variant="danger" onClick={() => onDelete(row)} />
                    )}
                </div>
            ),
        },
    ];

    return columns;
}
