import type { FC } from 'react';
import { useState } from 'react';
import { History } from 'lucide-react';

import { formatDateTimeEs } from '../../shared/utils/format-datetime-es';
import { useResource } from '../../shared/hooks/use-resource';
import { usePaginatedResource } from '../../shared/hooks/use-paginated-resource';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import { ActionButton } from '@ui/action-button/components/action-button';
import { Button } from '@ui/button/components/button';
import { Input } from '@ui/forms/components/input';
import { ToggleSwitchInput } from '@ui/forms/components/toggle-switch-input';
import { Modal } from '@ui/modal/components/modal';
import { ModuleListPage } from '@ui/module/components/module-list-page';
import { DataTable } from '@ui/table/components/data-table';
import { Pagination } from '@ui/table/components/pagination';
import { Tooltip } from '@ui/tooltip/components/tooltip';
import { SCHEDULED_TASK_LABELS, SCHEDULED_TASK_RESULT_CLASSES, SCHEDULED_TASK_RESULT_LABELS } from '../constants/scheduled-task-labels';
import type { ApiEjecucionTareaProgramada, ApiTareaProgramada } from '../types/api-tarea-programada';

/** Solo superadmin: hora y pausa del catálogo fijo de tareas programadas (`routes/console.php` las lee de aquí). */
export const ScheduledTasksPage: FC = () => {
    const { data, isLoading, refetch } = useResource<{ tareas: ApiTareaProgramada[] }>('/tareas-programadas');
    const tareas = data?.tareas ?? [];

    const { sendRequest } = useSendRequest();
    const [horasBorrador, setHorasBorrador] = useState<Record<string, string>>({});
    const [guardando, setGuardando] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [historialDe, setHistorialDe] = useState<ApiTareaProgramada | null>(null);

    const horaDe = (tarea: ApiTareaProgramada) => horasBorrador[tarea.clave] ?? tarea.hora;
    const horaCambiada = (tarea: ApiTareaProgramada) => horaDe(tarea) !== tarea.hora;

    const guardar = async (tarea: ApiTareaProgramada, cambios: { hora?: string; activa?: boolean }) => {
        setError(null);
        setGuardando(tarea.clave);

        try {
            await sendRequest(`/tareas-programadas/${encodeURIComponent(tarea.clave)}`, {
                method: 'PATCH',
                body: { hora: cambios.hora ?? horaDe(tarea), activa: cambios.activa ?? tarea.activa },
            });

            setHorasBorrador((actual) => Object.fromEntries(Object.entries(actual).filter(([clave]) => clave !== tarea.clave)));
            void refetch();
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setGuardando(null);
        }
    };

    return (
        <ModuleListPage
            pageTitle="Tareas programadas"
            countLabel={`${tareas.length} ${tareas.length === 1 ? 'tarea' : 'tareas'}`}
            errorMessage={error}
            onDismissError={() => setError(null)}
        >
            <DataTable<ApiTareaProgramada>
                columns={[
                    { key: 'clave', label: 'Tarea', render: (tarea) => SCHEDULED_TASK_LABELS[tarea.clave] ?? tarea.clave },
                    {
                        key: 'hora',
                        label: 'Hora',
                        render: (tarea) => (
                            <div className="flex items-center gap-2">
                                <Input
                                    id={`hora-${tarea.clave}`}
                                    type="time"
                                    className="w-32"
                                    value={horaDe(tarea)}
                                    onChange={(event) => setHorasBorrador((actual) => ({ ...actual, [tarea.clave]: event.target.value }))}
                                />
                                {horaCambiada(tarea) && (
                                    <Button size="sm" disabled={guardando === tarea.clave} onClick={() => void guardar(tarea, {})}>
                                        {guardando === tarea.clave ? 'Guardando…' : 'Guardar'}
                                    </Button>
                                )}
                            </div>
                        ),
                    },
                    {
                        key: 'activa',
                        label: 'Activa',
                        render: (tarea) =>
                            tarea.pausable ? (
                                <ToggleSwitchInput
                                    id={`activa-${tarea.clave}`}
                                    checked={tarea.activa}
                                    disabled={guardando === tarea.clave}
                                    onChange={(activa) => void guardar(tarea, { activa })}
                                />
                            ) : (
                                <Tooltip label="El respaldo automático no se puede pausar">
                                    <span className="inline-block" aria-label="El respaldo automático no se puede pausar">
                                        <ToggleSwitchInput id={`activa-${tarea.clave}`} checked disabled onChange={() => {}} />
                                    </span>
                                </Tooltip>
                            ),
                    },
                    {
                        key: 'ultima_ejecucion',
                        label: 'Última ejecución',
                        render: (tarea) =>
                            tarea.ultima_ejecucion ? (
                                <div className="flex items-center gap-2">
                                    <span
                                        className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${SCHEDULED_TASK_RESULT_CLASSES[tarea.ultima_ejecucion.resultado]}`}
                                    >
                                        {SCHEDULED_TASK_RESULT_LABELS[tarea.ultima_ejecucion.resultado]}
                                    </span>
                                    <span className="text-theme-xs text-gray-500 dark:text-gray-400">
                                        {formatDateTimeEs(tarea.ultima_ejecucion.finalizado_en)}
                                    </span>
                                </div>
                            ) : (
                                <span className="text-theme-xs text-gray-400 dark:text-gray-500">Sin ejecuciones aún</span>
                            ),
                    },
                    {
                        key: 'acciones',
                        label: 'Acción',
                        render: (tarea) => (
                            <ActionButton icon={<History className="size-5" />} label="Ver historial" onClick={() => setHistorialDe(tarea)} />
                        ),
                    },
                ]}
                rows={tareas}
                getRowKey={(row) => row.clave}
                loading={isLoading}
                emptyMessage="Sin tareas programadas."
            />

            <HistorialModal tarea={historialDe} onClose={() => setHistorialDe(null)} />
        </ModuleListPage>
    );
};

const HistorialModal: FC<{ tarea: ApiTareaProgramada | null; onClose: () => void }> = ({ tarea, onClose }) => {
    const endpoint = tarea ? `/tareas-programadas/${encodeURIComponent(tarea.clave)}/historial` : null;
    const { items, isLoading, page, setPage, totalPages } = usePaginatedResource<ApiEjecucionTareaProgramada>(endpoint);

    return (
        <Modal isOpen={tarea !== null} onClose={onClose} size="lg">
            {tarea && (
                <div className="flex flex-col gap-5 pr-10">
                    <h3 className="text-lg font-semibold text-gray-800 dark:text-white/90">
                        Historial · {SCHEDULED_TASK_LABELS[tarea.clave] ?? tarea.clave}
                    </h3>

                    <DataTable<ApiEjecucionTareaProgramada>
                        columns={[
                            { key: 'iniciado_en', label: 'Inicio', render: (ejecucion) => formatDateTimeEs(ejecucion.iniciado_en) },
                            { key: 'finalizado_en', label: 'Fin', render: (ejecucion) => formatDateTimeEs(ejecucion.finalizado_en) },
                            {
                                key: 'resultado',
                                label: 'Resultado',
                                render: (ejecucion) => (
                                    <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${SCHEDULED_TASK_RESULT_CLASSES[ejecucion.resultado]}`}>
                                        {SCHEDULED_TASK_RESULT_LABELS[ejecucion.resultado]}
                                    </span>
                                ),
                            },
                            { key: 'error', label: 'Error', render: (ejecucion) => ejecucion.error ?? '—' },
                        ]}
                        rows={items}
                        getRowKey={(row) => row.id}
                        loading={isLoading}
                        emptyMessage="Sin ejecuciones registradas."
                        maxHeightClassName="max-h-[50vh]"
                    />

                    <Pagination page={page} totalPages={totalPages} onPageChange={setPage} />
                </div>
            )}
        </Modal>
    );
};
