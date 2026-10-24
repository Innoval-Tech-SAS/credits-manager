import type { FC } from 'react';

import { FormSkeletonBody } from './form-skeleton-body';

/** Silueta de las páginas de crear/editar: la tarjeta del formulario ocupando el alto disponible. */
export const FormPageSkeleton: FC = () => (
    <div className="flex-1 rounded-2xl border border-gray-200 bg-white p-6 md:p-8 dark:border-gray-800 dark:bg-white/3">
        <FormSkeletonBody announce={false} />
    </div>
);
