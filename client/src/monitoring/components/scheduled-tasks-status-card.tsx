import type { FC } from 'react';
import { useNavigate } from 'react-router';

import { Button } from '@ui/button/components/button';
import { DataTable } from '@ui/table/components/data-table';
import { ArrowRight } from 'lucide-react';
import { SCHEDULED_TASK_LABELS, SCHEDULED_TASK_RESULT_CLASSES, SCHEDULED_TASK_RESULT_LABELS } from '../constants/scheduled-task-labels';
import type { ScheduledTasksStatusCardProps } from '../props/scheduled-tasks-status-card-props';
import { formatDateTimeEs } from '../../shared/utils/format-datetime-es';

/** Última ejecución de cada tarea programada: si algo falló recientemente, se ve aquí sin ir a la pantalla de detalle. */
export const ScheduledTasksStatusCard: FC<ScheduledTasksStatusCardProps> = ({ tareasProgramadas }) => {
    const navigate = useNavigate();

    const filas = Object.entries(tareasProgramadas)
        .filter((entrada): entrada is [string, NonNullable<(typeof tareasProgramadas)[string]>] => entrada[1] !== undefined)
        .map(([clave, ultima]) => ({ clave, ...ultima }));

    return (
        <section className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3 sm:p-6">
            <h2 className="mb-4 text-lg font-semibold text-gray-800 dark:text-white/90">Tareas programadas</h2>

            <DataTable
                columns={[
                    { key: 'clave', label: 'Tarea', render: (row) => SCHEDULED_TASK_LABELS[row.clave] ?? row.clave },
                    {
                        key: 'resultado',
                        label: 'Resultado',
                        render: (row) => (
                            <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap ${SCHEDULED_TASK_RESULT_CLASSES[row.resultado]}`}>
                                {SCHEDULED_TASK_RESULT_LABELS[row.resultado]}
                            </span>
                        ),
                    },
                    { key: 'finalizado_en', label: 'Última ejecución', render: (row) => formatDateTimeEs(row.finalizado_en) },
                ]}
                rows={filas}
                getRowKey={(row) => row.clave}
                emptyMessage="Todavía no corre ninguna tarea programada."
            />

            <div className="mt-4 border-t border-gray-100 pt-4 dark:border-gray-800">
                <Button size="sm" variant="outline" endIcon={<ArrowRight className="size-4" />} onClick={() => navigate('/tareas-programadas')}>
                    Administrar tareas
                </Button>
            </div>
        </section>
    );
};
