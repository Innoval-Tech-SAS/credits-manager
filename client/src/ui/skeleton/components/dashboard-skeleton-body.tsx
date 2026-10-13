import type { FC } from 'react';

import type { SkeletonBodyProps } from '../props/skeleton-body-props';
import { MoraCardSkeleton } from './mora-card-skeleton';
import { SkeletonBar } from './skeleton-bar';
import { SkeletonStatus } from './skeleton-status';
import { StatCardSkeleton } from './stat-card-skeleton';

const TARJETAS_CON_TENDENCIA = 3;
const TARJETAS_SIN_TENDENCIA = 4;
const PAGOS = 4;

/** Alturas de las columnas del histórico: dibujan un gráfico de barras en vez de líneas horizontales. */
const ALTURAS_HISTORICO = ['h-1/3', 'h-1/2', 'h-2/5', 'h-3/5', 'h-1/2', 'h-4/5', 'h-2/3', 'h-3/4', 'h-1/2', 'h-5/6', 'h-2/3', 'h-full'] as const;

/**
 * Contenido del inicio debajo de los filtros mientras llega el resumen: tarjetas de métricas, pagos del día con la
 * cartera al lado y el histórico ocupando el resto del alto.
 */
export const DashboardSkeletonBody: FC<SkeletonBodyProps> = ({ announce }) => (
    <SkeletonStatus announce={announce} className="flex flex-1 flex-col">
        {/* Dos filas, como `DashboardMetricCards`: las que llevan insignia de tendencia aparte de las de solo conteo,
            para que ninguna quede estirada con espacio vacío igualando la altura de la otra fila. */}
        <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6 xl:grid-cols-3">
            {Array.from({ length: TARJETAS_CON_TENDENCIA }, (_, indice) => (
                <StatCardSkeleton key={indice} conTendencia />
            ))}
        </div>

        <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6 xl:grid-cols-4">
            {Array.from({ length: TARJETAS_SIN_TENDENCIA }, (_, indice) => (
                <StatCardSkeleton key={indice} />
            ))}
        </div>

        <MoraCardSkeleton />

        <div className="mb-6 grid grid-cols-1 gap-4 md:gap-6 xl:grid-cols-12">
            <div className="rounded-2xl border border-gray-200 bg-white p-5 md:p-6 xl:col-span-7 dark:border-gray-800 dark:bg-white/3">
                <SkeletonBar className="mb-5 h-5 w-36" />
                <div className="flex flex-col gap-4">
                    {Array.from({ length: PAGOS }, (_, indice) => (
                        <div key={indice} className="flex items-center gap-3">
                            <SkeletonBar className="size-10 rounded-full" />
                            <div className="flex flex-1 flex-col gap-2">
                                <SkeletonBar className="h-4 w-2/5" />
                                <SkeletonBar className="h-3 w-1/4" />
                            </div>
                            <SkeletonBar className="h-4 w-20" />
                        </div>
                    ))}
                </div>
            </div>

            <div className="rounded-2xl border border-gray-200 bg-white p-5 md:p-6 xl:col-span-5 dark:border-gray-800 dark:bg-white/3">
                <SkeletonBar className="mb-5 h-5 w-28" />
                <div className="flex items-center justify-center py-2">
                    <SkeletonBar className="size-44 rounded-full" />
                </div>
            </div>
        </div>

        <div className="flex flex-1 flex-col rounded-2xl border border-gray-200 bg-white p-5 md:p-6 dark:border-gray-800 dark:bg-white/3">
            <SkeletonBar className="mb-6 h-5 w-40" />
            {/* Alto fijo: las alturas en % de las columnas necesitan un padre con altura definida. */}
            <div className="flex h-56 items-end gap-2 sm:gap-4">
                {ALTURAS_HISTORICO.map((altura, indice) => (
                    <SkeletonBar key={indice} className={`flex-1 rounded-md ${altura}`} />
                ))}
            </div>
        </div>
    </SkeletonStatus>
);
