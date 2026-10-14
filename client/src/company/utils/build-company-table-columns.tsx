import type { DataTableColumn } from '@ui/table/props/data-table-props';
import { LayoutDashboard } from 'lucide-react';
import { ActionButton } from '@ui/action-button/components/action-button';
import type { ApiCompany } from '../types/api-company';
import type { BuildCompanyTableColumnsOptions } from '../types/build-company-table-columns-options';

export function buildCompanyTableColumns({ onVerDashboard }: BuildCompanyTableColumnsOptions): DataTableColumn<ApiCompany>[] {
    return [
        { key: 'nombre_empresa', label: 'Empresa', render: (row) => row.nombre_empresa },
        { key: 'nit', label: 'NIT', render: (row) => row.nit ?? '—' },
        { key: 'municipio', label: 'Municipio', render: (row) => row.municipio?.nombre ?? '—' },
        { key: 'correo', label: 'Correo', render: (row) => row.correo ?? '—' },
        { key: 'telefono', label: 'Teléfono', render: (row) => row.telefono ?? '—' },
        {
            key: 'creado_en',
            label: 'Creada',
            render: (row) => (row.creado_en ? new Date(row.creado_en).toLocaleDateString('es-CO') : '—'),
        },
        {
            key: 'acciones',
            label: 'Acción',
            render: (row) => (
                <div className="flex w-full items-center gap-3">
                    <ActionButton icon={<LayoutDashboard className="size-5" />} label="Ver dashboard" onClick={() => onVerDashboard(row)} />
                </div>
            ),
        },
    ];
}
