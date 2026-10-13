import type { DataTableColumn } from '@ui/table/props/data-table-props';
import { RotateCcw } from 'lucide-react';
import { ActionButton } from '@ui/action-button/components/action-button';
import { formatDateTimeEs } from '../../shared/utils/format-datetime-es';
import { QUEUE_LABELS } from '../constants/queue-labels';
import type { ApiFailedJob } from '../types/api-failed-job';

export function buildFailedJobsTableColumns(onRetry: (job: ApiFailedJob) => void, retryingUuid: string | null): DataTableColumn<ApiFailedJob>[] {
    return [
        { key: 'fallido_en', label: 'Falló', render: (job) => <span className="whitespace-nowrap">{formatDateTimeEs(job.fallido_en)}</span> },
        {
            key: 'trabajo',
            label: 'Trabajo',
            render: (job) => (
                <div className="min-w-40">
                    <p className="text-sm text-gray-800 dark:text-white/90">{job.trabajo}</p>
                    <p className="text-xs text-gray-500 dark:text-gray-400">{QUEUE_LABELS[job.cola] ?? job.cola}</p>
                </div>
            ),
        },
        {
            key: 'error',
            label: 'Error',
            render: (job) => (
                <span className="block max-w-72 truncate text-xs text-gray-500 lg:max-w-md dark:text-gray-400" title={job.error}>
                    {job.error || '—'}
                </span>
            ),
        },
        {
            key: 'acciones',
            label: 'Acción',
            render: (job) => (
                <ActionButton
                    icon={<RotateCcw className="size-5" />}
                    label={retryingUuid === job.uuid ? 'Reintentando…' : 'Reintentar'}
                    disabled={retryingUuid !== null}
                    onClick={() => onRetry(job)}
                />
            ),
        },
    ];
}
