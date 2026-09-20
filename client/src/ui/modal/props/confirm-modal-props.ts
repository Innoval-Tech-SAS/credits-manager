import type { ModalVariant } from '../types/modal-variant';

export type ConfirmModalProps = {
    isOpen: boolean;
    onClose: () => void;
    onConfirm: () => void;
    variant?: ModalVariant;
    title: string;
    description: string;
    confirmLabel?: string;
    cancelLabel?: string;
    isConfirming?: boolean;
};
