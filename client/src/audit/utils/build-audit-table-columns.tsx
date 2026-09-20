import type { DataTableColumn } from '@ui/table/props/data-table-props';
import { Eye } from 'lucide-react';
import type { ApiCompany } from '../../company/types/api-company';
import { formatDateTimeEs } from '../../shared/utils/format-datetime-es';
import { AuditActionBadge } from '../components/audit-action-badge';
import { AUDIT_MODULE_LABELS } from '../constants/audit-modules';
import type { ApiAuditEvent } from '../types/api-audit-event';

export function buildAuditTableColumns(companies: ApiCompany[] | null, onView: (event: ApiAuditEvent) => void): DataTableColumn<ApiAuditEvent>[] {
    const companyColumn: DataTableColumn<ApiAuditEvent>[] =
        companies === null
            ? []
            : [
                  {
                      key: 'empresa',
                      label: 'Empresa',
                      render: (event) =>
                          event.empresa_id === null
                              ? 'Plataforma'
                              : (companies.find((company) => company.id === event.empresa_id)?.nombre_empresa ?? `#${event.empresa_id}`),
                  },
              ];

    return [
        { key: 'fecha_hora', label: 'Fecha y hora', render: (event) => <span className="whitespace-nowrap">{formatDateTimeEs(event.fecha_hora)}</span> },
        {
            key: 'autor',
            label: 'Autor',
            render: (event) => (
                <div className="min-w-32">
                    <p className="text-sm text-gray-800 dark:text-white/90">{event.autor_nombre ?? '—'}</p>
                    <p className="text-xs text-gray-500 dark:text-gray-400">
                        {event.autor_usuario ? `@${event.autor_usuario}` : ''}
                        {event.es_superadmin ? ' · Superadmin' : event.autor_rol ? ` · ${event.autor_rol}` : ''}
                    </p>
                </div>
            ),
        },
        ...companyColumn,
        { key: 'modulo', label: 'Módulo', render: (event) => AUDIT_MODULE_LABELS[event.modulo] ?? event.modulo },
        { key: 'accion', label: 'Acción', render: (event) => <AuditActionBadge action={event.accion} /> },
        {
            key: 'descripcion',
            label: 'Descripción',
            render: (event) => (
                <span className="block max-w-xs truncate lg:max-w-md" title={event.descripcion}>
                    {event.descripcion}
                </span>
            ),
        },
        {
            key: 'entidad',
            label: 'Entidad',
            render: (event) => (event.entidad ? `${event.entidad}${event.entidad_id !== null ? ` #${event.entidad_id}` : ''}` : '—'),
        },
        {
            key: 'detalle',
            label: 'Detalle',
            render: (event) => (
                <button
                    type="button"
                    onClick={() => onView(event)}
                    aria-label="Ver detalle del evento"
                    className="text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white/90"
                >
                    <Eye className="size-5" />
                </button>
            ),
        },
    ];
}
