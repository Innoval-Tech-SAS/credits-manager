import type { FC } from 'react';
import { twMerge } from 'tailwind-merge';

import type { SkeletonBarProps } from '../props/skeleton-bar-props';

export const SkeletonBar: FC<SkeletonBarProps> = ({ className = 'h-4 w-full' }) => (
    <div aria-hidden="true" className={twMerge('animate-pulse rounded-lg bg-gray-200 dark:bg-gray-800', className)} />
);
