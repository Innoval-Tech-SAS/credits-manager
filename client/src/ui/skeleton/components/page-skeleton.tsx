import type { FC } from 'react';
import { useLocation } from 'react-router';

import type { PageSkeletonProps } from '../props/page-skeleton-props';
import { resolvePageSkeletonVariant } from '../utils/resolve-page-skeleton-variant';
import { CalendarPageSkeleton } from './calendar-page-skeleton';
import { DashboardPageSkeleton } from './dashboard-page-skeleton';
import { DetailPageSkeleton } from './detail-page-skeleton';
import { FormPageSkeleton } from './form-page-skeleton';
import { ListPageSkeleton } from './list-page-skeleton';
import { ReportPageSkeleton } from './report-page-skeleton';
import { SkeletonStatus } from './skeleton-status';
import { SummaryPageSkeleton } from './summary-page-skeleton';
import { SuperadminHomePageSkeleton } from './superadmin-home-page-skeleton';

const SILUETAS = {
    dashboard: DashboardPageSkeleton,
    'superadmin-home': SuperadminHomePageSkeleton,
    list: ListPageSkeleton,
    form: FormPageSkeleton,
    detail: DetailPageSkeleton,
    report: ReportPageSkeleton,
    summary: SummaryPageSkeleton,
    calendar: CalendarPageSkeleton,
} as const;

/**
 * Silueta del tipo de pantalla de la ruta actual (listado, formulario, detalle, informe, resumen, calendario o inicio).
 * La usa el layout mientras llega el código de una página y las páginas mientras llegan sus datos, para que la silueta
 * no cambie entre una espera y la otra. `esSuperAdmin` lo pasa quien conozca la sesión (este componente, en `ui/`, no
 * depende de `auth/`): solo cambia la silueta de `/`, donde el inicio de superadmin no es el dashboard completo.
 */
export const PageSkeleton: FC<PageSkeletonProps> = ({ esSuperAdmin = false }) => {
    const { pathname } = useLocation();
    const Silueta = SILUETAS[resolvePageSkeletonVariant(pathname, esSuperAdmin)];

    return (
        <SkeletonStatus className="flex min-h-full flex-col pb-6">
            <Silueta />
        </SkeletonStatus>
    );
};
