import { Link } from 'react-router';

import type { DataTableColumn } from '@ui/table/props/data-table-props';
import { Pencil } from 'lucide-react';
import { THIRD_PARTY_TYPE_BADGE_CLASSES, THIRD_PARTY_TYPE_BADGE_LABEL } from '../constants/third-party-type-badge-styles';
import { PERSON_TYPE_LABELS } from '../constants/person-type-choices';
import type { ApiThirdParty } from '../types/api-third-party';
import { formatThirdPartyDocument } from './format-third-party-document';

export function buildThirdPartyTableColumns(canEdit: boolean): DataTableColumn<ApiThirdParty>[] {
    const columns: DataTableColumn<ApiThirdParty>[] = [
        { key: 'nombre', label: 'Nombre', sortable: true },
        {
            key: 'tipo',
            label: 'Tipo',
            render: (row) =>
                row.tipo ? (
                    <span
                        className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${THIRD_PARTY_TYPE_BADGE_CLASSES[row.tipo]}`}
                    >
                        {THIRD_PARTY_TYPE_BADGE_LABEL[row.tipo]}
                    </span>
                ) : (
                    <span className="text-gray-400 dark:text-gray-500">Sin asignar</span>
                ),
        },
        { key: 'tipo_persona', label: 'Persona', render: (row) => PERSON_TYPE_LABELS[row.tipo_persona] },
        { key: 'numero_documento', label: 'Documento', render: (row) => formatThirdPartyDocument(row) },
        { key: 'ciudad', label: 'Ciudad', render: (row) => row.ciudad ?? '—' },
        { key: 'correo', label: 'Correo', render: (row) => row.correo ?? '—' },
        {
            key: 'activo',
            label: 'Estado',
            render: (row) => (
                <span
                    className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${
                        row.activo
                            ? 'bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-400'
                            : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'
                    }`}
                >
                    {row.activo ? 'Activo' : 'Inactivo'}
                </span>
            ),
        },
    ];

    if (canEdit) {
        columns.push({
            key: 'acciones',
            label: '',
            render: (row) => (
                <Link to={`/terceros/${row.id}/editar`} className="text-brand-600 hover:underline dark:text-brand-400">
                    <Pencil className="size-4" />
                </Link>
            ),
        });
    }

    return columns;
}
