import type { ReactNode } from 'react';

import type { SkeletonBodyProps } from './skeleton-body-props';

export type SkeletonStatusProps = SkeletonBodyProps & {
    className?: string;
    children: ReactNode;
};
