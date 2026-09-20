import type { ReactNode } from 'react';

export type DropdownProps = {
    isOpen: boolean;
    onClose: () => void;
    children: ReactNode;
    className?: string;
};
