import type { FC } from 'react';

import type { SkeletonStatusProps } from '../props/skeleton-status-props';

/** Envoltorio de un esqueleto: lo anuncia como "Cargando…" a lectores de pantalla, salvo que `announce` sea `false`. */
export const SkeletonStatus: FC<SkeletonStatusProps> = ({ announce = true, className, children }) =>
    announce ? (
        <div role="status" aria-busy="true" className={className}>
            <span className="sr-only">Cargando…</span>
            {children}
        </div>
    ) : (
        <div className={className}>{children}</div>
    );
