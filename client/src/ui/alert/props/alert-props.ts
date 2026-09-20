import type { ModalVariant } from '@ui/modal/types/modal-variant';

export type AlertProps = {
    variant: ModalVariant;
    title: string;
    message: string;
    onDismiss?: () => void;
};
