import type { FC } from 'react';

import { StatCardSkeleton } from './stat-card-skeleton';

const TARJETAS = 6;

/** Silueta del inicio de superadmin: las 6 tarjetas de plataforma del panel propio (ver `SuperadminHomeCards`). */
export const SuperadminHomePageSkeleton: FC = () => (
    <div className="grid flex-1 grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6 xl:grid-cols-3">
        {Array.from({ length: TARJETAS }, (_, indice) => (
            <StatCardSkeleton key={indice} />
        ))}
    </div>
);
