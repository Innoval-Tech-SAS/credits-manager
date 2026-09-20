import type { ReactNode } from 'react';

import type { ModalSize } from '../types/modal-size';

export type ModalProps = {
    isOpen: boolean;
    onClose: () => void;
    size?: ModalSize;
    showCloseButton?: boolean;
    className?: string;
    children: ReactNode;
};
