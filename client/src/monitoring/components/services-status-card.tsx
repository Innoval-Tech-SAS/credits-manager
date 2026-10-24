import type { FC } from 'react';
import { useState } from 'react';

import { Modal } from '@ui/modal/components/modal';
import { formatDateTimeEs } from '../../shared/utils/format-datetime-es';
import { SERVICE_LABELS } from '../constants/service-labels';
import { SERVICE_STATUS_CLASSES, SERVICE_STATUS_DOT_CLASSES, SERVICE_STATUS_LABELS } from '../constants/service-status';
import type { ServicesStatusCardProps } from '../props/services-status-card-props';
import type { ApiServiceState } from '../types/api-system-health';

export const ServicesStatusCard: FC<ServicesStatusCardProps> = ({ servicios }) => {
    const [seleccionado, setSeleccionado] = useState<ApiServiceState | null>(null);
    const caidos = servicios.filter((servicio) => servicio.estado === 'caido').length;

    return (
        <section className="h-full rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3 sm:p-6">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">Servicios</h2>
                <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${SERVICE_STATUS_CLASSES[caidos > 0 ? 'caido' : 'activo']}`}>
                    {caidos > 0 ? `${caidos} caído${caidos === 1 ? '' : 's'}` : 'Todos activos'}
                </span>
            </div>

            {/* Tope de altura para que una lista larga de servicios no deforme la gráfica de uso de al lado. */}
            <ul className="max-h-[22rem] divide-y divide-gray-100 overflow-y-auto custom-scrollbar dark:divide-gray-800">
                {servicios.map((servicio) => {
                    const etiqueta = SERVICE_LABELS[servicio.servicio] ?? { nombre: servicio.servicio, rol: '' };
                    // Un worker ocupado trae la clase completa del job: basta su nombre corto.
                    const detalle = servicio.detalle ? (servicio.detalle.split('\\').pop() ?? servicio.detalle) : etiqueta.rol;

                    return (
                        <li key={servicio.servicio}>
                            <button
                                type="button"
                                onClick={() => setSeleccionado(servicio)}
                                className="-mx-2 flex w-full items-start justify-between gap-3 rounded-lg px-2 py-3 text-left transition-colors hover:bg-gray-50 dark:hover:bg-white/5"
                            >
                                <div className="flex min-w-0 items-start gap-3">
                                    <span className={`mt-1.5 size-2 shrink-0 rounded-full ${SERVICE_STATUS_DOT_CLASSES[servicio.estado]}`} />
                                    <div className="min-w-0">
                                        <p className="text-sm font-medium text-gray-800 dark:text-white/90">{etiqueta.nombre}</p>
                                        <p className="truncate text-theme-xs text-gray-500 dark:text-gray-400" title={servicio.detalle ?? etiqueta.rol}>
                                            {detalle}
                                            {servicio.ultimo_latido && servicio.estado === 'ocupado'
                                                ? ` · desde ${formatDateTimeEs(servicio.ultimo_latido)}`
                                                : ''}
                                        </p>
                                    </div>
                                </div>

                                <span
                                    className={`shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap ${SERVICE_STATUS_CLASSES[servicio.estado]}`}
                                >
                                    {SERVICE_STATUS_LABELS[servicio.estado]}
                                </span>
                            </button>
                        </li>
                    );
                })}
            </ul>

            <Modal isOpen={seleccionado !== null} onClose={() => setSeleccionado(null)} size="sm">
                {seleccionado && <DetalleServicio servicio={seleccionado} />}
            </Modal>
        </section>
    );
};

const DetalleServicio: FC<{ servicio: ApiServiceState }> = ({ servicio }) => {
    const etiqueta = SERVICE_LABELS[servicio.servicio] ?? { nombre: servicio.servicio, rol: '' };

    return (
        <div className="flex flex-col gap-5 pr-10">
            <div className="flex flex-col gap-1">
                <h3 className="text-lg font-semibold text-gray-800 dark:text-white/90">{etiqueta.nombre}</h3>
                {etiqueta.rol && <p className="text-sm text-gray-500 dark:text-gray-400">{etiqueta.rol}</p>}
            </div>

            <dl className="flex flex-col gap-4">
                <div>
                    <dt className="text-theme-xs font-medium text-gray-500 dark:text-gray-400">Estado</dt>
                    <dd className="mt-1">
                        <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${SERVICE_STATUS_CLASSES[servicio.estado]}`}>
                            {SERVICE_STATUS_LABELS[servicio.estado]}
                        </span>
                    </dd>
                </div>

                <div>
                    <dt className="text-theme-xs font-medium text-gray-500 dark:text-gray-400">Detalle</dt>
                    <dd className="mt-1 text-sm text-gray-800 dark:text-white/90">{servicio.detalle ?? '—'}</dd>
                </div>

                <div>
                    <dt className="text-theme-xs font-medium text-gray-500 dark:text-gray-400">Último latido</dt>
                    <dd className="mt-1 text-sm text-gray-800 dark:text-white/90">
                        {servicio.ultimo_latido ? formatDateTimeEs(servicio.ultimo_latido) : '—'}
                    </dd>
                </div>
            </dl>
        </div>
    );
};
