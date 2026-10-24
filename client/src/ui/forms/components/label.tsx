import type { FC } from 'react';
import { twMerge } from 'tailwind-merge';

import type { LabelProps } from '../props/label-props';

export const Label: FC<LabelProps> = ({ htmlFor, children, className }) => {
    return (
        <label
            htmlFor={htmlFor}
            className={twMerge('mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400', className)}
        >
            {children}
        </label>
    );
};
