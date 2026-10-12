import type { FC } from 'react';

import type { SkeletonBarProps } from '../props/skeleton-bar-props';

export const SkeletonBar: FC<SkeletonBarProps> = ({ className = 'h-4 w-full' }) => (
    <div aria-hidden="true" className={`animate-pulse rounded-lg bg-gray-200 dark:bg-gray-800 ${className}`} />
);
