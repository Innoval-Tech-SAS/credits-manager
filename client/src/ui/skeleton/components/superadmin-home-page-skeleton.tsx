import type { FC } from 'react';

import { SuperadminHomeSkeletonBody } from './superadmin-home-skeleton-body';

/** Silueta de ruta del home del superadmin, mientras llega el bundle de la página (el contenido real vive en `SuperadminHomeSkeletonBody`). */
export const SuperadminHomePageSkeleton: FC = () => <SuperadminHomeSkeletonBody announce={false} />;
