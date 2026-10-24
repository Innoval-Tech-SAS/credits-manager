import type { ReactNode } from 'react';

export type DropdownItemProps = {
    tag?: 'button' | 'link';
    to?: string;
    onClick?: () => void;
    onItemClick?: () => void;
    className?: string;
    children: ReactNode;
};
