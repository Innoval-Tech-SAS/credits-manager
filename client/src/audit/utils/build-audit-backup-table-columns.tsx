import type { DataTableColumn } from '@ui/table/props/data-table-props';
import { RotateCcw } from 'lucide-react';
import { formatDateTimeEs } from '../../shared/utils/format-datetime-es';
import { AUDIT_ACTION_LABELS } from '../constants/audit-actions';
import { AUDIT_BACKUP_REASON_LABELS, AUDIT_BACKUP_STATUS_CLASSES, AUDIT_BACKUP_STATUS_LABELS } from '../constants/audit-backup';
import { AUDIT_MODULE_LABELS } from '../constants/audit-modules';
import type { ApiAuditBackupEntry } from '../types/api-audit-backup-entry';
import type { AuditAction } from '../types/audit-action';

export function buildAuditBackupTableColumns(
    onRecover: (entry: ApiAuditBackupEntry) => void,
    recoveringId: string | null,
): DataTableColumn<ApiAuditBackupEntry>[] {
    return [
        { key: 'registrado_en', label: 'Registrado', render: (entry) => <span className="whitespace-nowrap">{formatDateTimeEs(entry.registrado_en)}</span> },
        {
            key: 'descripcion',
            label: 'Evento',
            render: (entry) => (
                <div className="min-w-48">
                    <p className="max-w-xs truncate text-sm text-gray-800 lg:max-w-md dark:text-white/90" title={entry.descripcion ?? ''}>
                        {entry.descripcion ?? '—'}
                    </p>
                    <p className="text-xs text-gray-500 dark:text-gray-400">
                        {entry.modulo ? (AUDIT_MODULE_LABELS[entry.modulo] ?? entry.modulo) : ''}
                        {entry.accion ? ` · ${AUDIT_ACTION_LABELS[entry.accion as AuditAction] ?? entry.accion}` : ''}
                    </p>
                </div>
            ),
        },
        { key: 'motivo_fallo', label: 'Motivo del fallo', render: (entry) => AUDIT_BACKUP_REASON_LABELS[entry.motivo_fallo] ?? entry.motivo_fallo },
        {
            key: 'error',
            label: 'Error',
            render: (entry) => (
                <span className="block max-w-56 truncate text-xs text-gray-500 dark:text-gray-400" title={entry.error}>
                    {entry.error || '—'}
                </span>
            ),
        },
        {
            key: 'estado',
            label: 'Estado',
            render: (entry) => (
                <span className={`inline-block rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap ${AUDIT_BACKUP_STATUS_CLASSES[entry.estado]}`}>
                    {AUDIT_BACKUP_STATUS_LABELS[entry.estado]}
                </span>
            ),
        },
        {
            key: 'acciones',
            label: 'Acción',
            render: (entry) =>
                entry.estado === 'pendiente' && entry.evento_id ? (
                    <button
                        type="button"
                        disabled={recoveringId !== null}
                        onClick={() => onRecover(entry)}
                        className="inline-flex items-center gap-1.5 text-sm font-medium text-brand-500 hover:text-brand-600 disabled:opacity-50 dark:text-brand-400"
                    >
                        <RotateCcw className="size-4" />
                        {recoveringId === entry.evento_id ? 'Recuperando…' : 'Recuperar'}
                    </button>
                ) : (
                    <span className="text-gray-400 dark:text-gray-500">—</span>
                ),
        },
    ];
}
