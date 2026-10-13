import type { FC } from 'react';
import { useState } from 'react';

import { Alert } from '@ui/alert/components/alert';
import { Select } from '@ui/forms/components/select';
import { DataTable } from '@ui/table/components/data-table';
import { Pagination } from '@ui/table/components/pagination';
import { usePaginatedResource } from '../../shared/hooks/use-paginated-resource';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import { QUEUE_OPTIONS } from '../constants/queue-labels';
import type { ApiFailedJob } from '../types/api-failed-job';
import { buildFailedJobsTableColumns } from '../utils/build-failed-jobs-table-columns';

export const FailedJobsCard: FC = () => {
    const { sendRequest } = useSendRequest();
    const [cola, setCola] = useState('');
    const [retryingUuid, setRetryingUuid] = useState<string | null>(null);
    const [successMessage, setSuccessMessage] = useState<string | null>(null);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    const { items, isLoading, error, page, setPage, totalPages, totalItems, refetch } = usePaginatedResource<ApiFailedJob>('/monitoreo/trabajos-fallidos', { cola });

    const retry = async (job: ApiFailedJob) => {
        setErrorMessage(null);
        setSuccessMessage(null);
        setRetryingUuid(job.uuid);

        try {
            await sendRequest(`/monitoreo/trabajos-fallidos/${job.uuid}/reintentar`, { method: 'POST' });
            setSuccessMessage(`${job.trabajo} volvió a la cola ${job.cola}.`);
            refetch();
        } catch (caught) {
            setErrorMessage(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setRetryingUuid(null);
        }
    };

    return (
        <section className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3 sm:p-6">
            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">Trabajos fallidos</h2>
                    <p className="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">
                        {totalItems} {totalItems === 1 ? 'trabajo' : 'trabajos'} · reintentar lo devuelve a su cola.
                    </p>
                </div>

                <div className="w-full sm:w-52">
                    <Select
                        id="filtro-cola-fallidos"
                        ariaLabel="Cola"
                        options={QUEUE_OPTIONS}
                        placeholder="Todas las colas"
                        placeholderSelectable
                        defaultValue={cola}
                        onChange={(value) => {
                            setCola(value);
                            setPage(1);
                        }}
                    />
                </div>
            </div>

            {successMessage && (
                <div className="mb-4">
                    <Alert variant="success" title="Listo" message={successMessage} onDismiss={() => setSuccessMessage(null)} />
                </div>
            )}

            {(errorMessage ?? error) && (
                <div className="mb-4">
                    <Alert variant="danger" title="Error" message={errorMessage ?? error ?? ''} onDismiss={() => setErrorMessage(null)} />
                </div>
            )}

            <DataTable
                columns={buildFailedJobsTableColumns((job) => void retry(job), retryingUuid)}
                rows={items}
                getRowKey={(job) => job.uuid}
                loading={isLoading}
                emptyMessage="No hay trabajos fallidos."
                maxHeightClassName="max-h-[60vh]"
            />

            <div className="mt-4">
                <Pagination page={page} totalPages={totalPages} onPageChange={setPage} />
            </div>
        </section>
    );
};
