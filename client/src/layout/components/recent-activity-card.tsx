import type { FC } from 'react';
import { Link } from 'react-router';

import { SkeletonBar } from '@ui/skeleton/components/skeleton-bar';
import { AUDIT_ACTION_BADGE_CLASSES, AUDIT_ACTION_LABELS } from '../../audit/constants/audit-actions';
import type { ApiAuditEvent } from '../../audit/types/api-audit-event';
import { useResource } from '../../shared/hooks/use-resource';
import { formatDateTimeEs } from '../../shared/utils/format-datetime-es';
import type { PaginatedResponse } from '../../shared/types/paginated-response';

const EVENTOS_A_MOSTRAR = 6;

/** Teaser de la bitácora en el home: últimos eventos entre todas las empresas, sin filtros ni paginación. */
export const RecentActivityCard: FC = () => {
    const { data: pagina, error } = useResource<PaginatedResponse<ApiAuditEvent>>(`/auditoria/bitacora?per_page=${EVENTOS_A_MOSTRAR}`);

    return (
        <section className="h-full rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3 sm:p-6">
            <div className="mb-4 flex items-center justify-between gap-2">
                <h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">Actividad reciente</h2>
                <Link to="/auditoria" className="text-theme-xs font-medium text-brand-500 hover:text-brand-600 dark:text-brand-400">
                    Ver bitácora
                </Link>
            </div>

            {error && <p className="text-sm text-error-600 dark:text-error-400">{error}</p>}

            {!pagina && !error && (
                <div className="flex flex-col gap-4">
                    {Array.from({ length: EVENTOS_A_MOSTRAR }, (_, indice) => (
                        <SkeletonBar key={indice} className="h-10 w-full" />
                    ))}
                </div>
            )}

            {pagina && pagina.data.length === 0 && (
                <p className="py-6 text-center text-sm text-gray-500 dark:text-gray-400">Aún no hay eventos registrados.</p>
            )}

            {pagina && pagina.data.length > 0 && (
                <ul className="divide-y divide-gray-100 dark:divide-gray-800">
                    {pagina.data.map((evento) => (
                        <li key={evento.id} className="flex items-start justify-between gap-3 py-3 first:pt-0 last:pb-0">
                            <div className="min-w-0">
                                <p className="truncate text-sm font-medium text-gray-800 dark:text-white/90">{evento.descripcion}</p>
                                <p className="text-theme-xs text-gray-500 dark:text-gray-400">
                                    {evento.autor_nombre ?? 'Sistema'} · {formatDateTimeEs(evento.fecha_hora)}
                                </p>
                            </div>
                            <span
                                className={`shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap ${AUDIT_ACTION_BADGE_CLASSES[evento.accion]}`}
                            >
                                {AUDIT_ACTION_LABELS[evento.accion]}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
};
