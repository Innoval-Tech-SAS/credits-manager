import type { FC } from 'react';

import { SkeletonBar } from './skeleton-bar';

/** Silueta de un campo de formulario: etiqueta (`Label`, `mb-1.5`) y control de 44 px (`Input`/`Select`/`DatePicker`). */
export const FieldSkeleton: FC = () => (
    <div>
        <SkeletonBar className="mb-1.5 h-4 w-28" />
        <SkeletonBar className="h-11 w-full" />
    </div>
);
