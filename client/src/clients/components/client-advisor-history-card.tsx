import type { FC } from 'react';
import { useState } from 'react';

import { ArrowRightLeft } from 'lucide-react';
import { useResource } from '../../shared/hooks/use-resource';
import { formatDateTimeEs } from '../../shared/utils/format-datetime-es';
import { Button } from '@ui/button/components/button';
import { DataTable } from '@ui/table/components/data-table';
import type { ClientAdvisorHistoryCardProps } from '../props/client-advisor-history-card-props';
import type { ApiAdvisorHistory } from '../types/api-advisor-history';
import { ReassignAdvisorModal } from './reassign-advisor-modal';

/** Solo la ve el administrador de la empresa: el historial de asesores no se muestra a los demás roles. */
export const ClientAdvisorHistoryCard: FC<ClientAdvisorHistoryCardProps> = ({ clientId, currentAdvisorId, onReassigned }) => {
    const { data, error, refetch } = useResource<{ historial: ApiAdvisorHistory[] }>(`/clientes/${clientId}/asesor-historial`);
    const [isReassigning, setIsReassigning] = useState(false);

    return (
        <div className="mb-6 rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">Historial de asesores</h2>
                <Button iconOnly startIcon={<ArrowRightLeft className="size-4" />} variant="outline" onClick={() => setIsReassigning(true)}>
                    Reasignar asesor
                </Button>
            </div>

            {error && <p className="mb-4 text-sm text-error-600 dark:text-error-400">{error}</p>}

            <DataTable
                columns={[
                    { key: 'fecha', label: 'Fecha', render: (row) => formatDateTimeEs(row.creado_en) },
                    { key: 'asesor', label: 'Asesor', render: (row) => row.asesor.nombre },
                    { key: 'anterior', label: 'Asesor anterior', render: (row) => row.asesor_anterior?.nombre ?? '—' },
                    { key: 'autor', label: 'Asignado por', render: (row) => row.autor?.nombre ?? '—' },
                    { key: 'motivo', label: 'Motivo', render: (row) => row.motivo ?? '—' },
                ]}
                rows={data?.historial ?? []}
                getRowKey={(row) => row.id}
                emptyMessage="Este cliente aún no tiene asignaciones registradas."
            />

            <ReassignAdvisorModal
                isOpen={isReassigning}
                onClose={() => setIsReassigning(false)}
                clientId={clientId}
                currentAdvisorId={currentAdvisorId}
                onReassigned={() => {
                    setIsReassigning(false);
                    refetch();
                    onReassigned();
                }}
            />
        </div>
    );
};
