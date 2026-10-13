import type { FC } from 'react';

import { formatDateTimeEs } from '../../shared/utils/format-datetime-es';
import { SERVICE_LABELS } from '../constants/service-labels';
import { SERVICE_STATUS_CLASSES, SERVICE_STATUS_DOT_CLASSES, SERVICE_STATUS_LABELS } from '../constants/service-status';
import type { ServicesStatusCardProps } from '../props/services-status-card-props';

export const ServicesStatusCard: FC<ServicesStatusCardProps> = ({ servicios }) => {
    const caidos = servicios.filter((servicio) => servicio.estado === 'caido').length;

    return (
        <section className="h-full rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3 sm:p-6">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">Servicios</h2>
                <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${SERVICE_STATUS_CLASSES[caidos > 0 ? 'caido' : 'activo']}`}>
                    {caidos > 0 ? `${caidos} caído${caidos === 1 ? '' : 's'}` : 'Todos activos'}
                </span>
            </div>

            <ul className="divide-y divide-gray-100 dark:divide-gray-800">
                {servicios.map((servicio) => {
                    const etiqueta = SERVICE_LABELS[servicio.servicio] ?? { nombre: servicio.servicio, rol: '' };
                    // Un worker ocupado trae la clase completa del job: basta su nombre corto.
                    const detalle = servicio.detalle ? (servicio.detalle.split('\\').pop() ?? servicio.detalle) : etiqueta.rol;

                    return (
                        <li key={servicio.servicio} className="flex items-start justify-between gap-3 py-3">
                            <div className="flex min-w-0 items-start gap-3">
                                <span className={`mt-1.5 size-2 shrink-0 rounded-full ${SERVICE_STATUS_DOT_CLASSES[servicio.estado]}`} />
                                <div className="min-w-0">
                                    <p className="text-sm font-medium text-gray-800 dark:text-white/90">{etiqueta.nombre}</p>
                                    <p className="truncate text-theme-xs text-gray-500 dark:text-gray-400" title={servicio.detalle ?? etiqueta.rol}>
                                        {detalle}
                                        {servicio.ultimo_latido && servicio.estado === 'ocupado' ? ` · desde ${formatDateTimeEs(servicio.ultimo_latido)}` : ''}
                                    </p>
                                </div>
                            </div>

                            <span className={`shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap ${SERVICE_STATUS_CLASSES[servicio.estado]}`}>
                                {SERVICE_STATUS_LABELS[servicio.estado]}
                            </span>
                        </li>
                    );
                })}
            </ul>
        </section>
    );
};
