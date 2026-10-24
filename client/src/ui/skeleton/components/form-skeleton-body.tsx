import type { FC } from 'react';

import type { SkeletonBodyProps } from '../props/skeleton-body-props';
import { FieldSkeleton } from './field-skeleton';
import { SkeletonBar } from './skeleton-bar';
import { SkeletonStatus } from './skeleton-status';

/** Una sección del formulario: título en versalitas y sus campos, con una fila de dos campos en medio. */
const SeccionSkeleton: FC = () => (
    <section>
        <SkeletonBar className="mb-4 h-3 w-32" />
        <div className="space-y-6">
            <FieldSkeleton />
            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <FieldSkeleton />
                <FieldSkeleton />
            </div>
            <FieldSkeleton />
        </div>
    </section>
);

/**
 * Contenido de un formulario mientras cargan sus datos: dos secciones de campos (lado a lado desde `xl`) y las acciones al pie.
 * Va dentro de la tarjeta de la página; `FormPageSkeleton` le pone la tarjeta cuando aún no hay página.
 */
export const FormSkeletonBody: FC<SkeletonBodyProps> = ({ announce }) => (
    <SkeletonStatus announce={announce}>
        <div className="flex flex-col gap-8 xl:grid xl:grid-cols-2 xl:items-start xl:gap-x-12">
            <SeccionSkeleton />
            <SeccionSkeleton />
        </div>

        <div className="mt-8 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <SkeletonBar className="h-11 w-full sm:w-28" />
            <SkeletonBar className="h-11 w-full sm:w-36" />
        </div>
    </SkeletonStatus>
);
