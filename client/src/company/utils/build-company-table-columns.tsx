import type { DataTableColumn } from '@ui/table/props/data-table-props';
import type { ApiCompany } from '../types/api-company';

export function buildCompanyTableColumns(): DataTableColumn<ApiCompany>[] {
    return [
        { key: 'nombre_empresa', label: 'Empresa', render: (row) => row.nombre_empresa },
        { key: 'nit', label: 'NIT', render: (row) => row.nit ?? '—' },
        { key: 'correo', label: 'Correo', render: (row) => row.correo ?? '—' },
        { key: 'telefono', label: 'Teléfono', render: (row) => row.telefono ?? '—' },
        {
            key: 'creado_en',
            label: 'Creada',
            render: (row) => (row.creado_en ? new Date(row.creado_en).toLocaleDateString('es-CO') : '—'),
        },
    ];
}
