import type { FC } from 'react';

import { Button } from '@ui/button/components/button';
import { Spinner } from '@ui/spinner/components/spinner';
import { MODAL_VARIANT_STYLES } from '../constants/modal-variant-styles';
import type { ConfirmModalProps } from '../props/confirm-modal-props';
import { Modal } from './modal';

export const ConfirmModal: FC<ConfirmModalProps> = ({
    isOpen,
    onClose,
    onConfirm,
    variant = 'danger',
    title,
    description,
    confirmLabel = 'Confirmar',
    cancelLabel = 'Cancelar',
    isConfirming = false,
}) => {
    const { iconWrapperClass, iconClass, confirmButtonClass, Icon } = MODAL_VARIANT_STYLES[variant];

    return (
        <Modal isOpen={isOpen} onClose={onClose} size="sm" showCloseButton={false}>
            <div className="text-center">
                <div className={`mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full ${iconWrapperClass}`}>
                    <Icon className={iconClass} />
                </div>

                <h4 className="mb-2 text-lg font-semibold text-gray-800 dark:text-white/90">{title}</h4>
                <p className="text-sm leading-6 text-gray-500 dark:text-gray-400">{description}</p>

                <div className="mt-7 flex flex-col-reverse items-center justify-center gap-3 sm:flex-row">
                    <Button variant="outline" className="w-full sm:w-auto" onClick={onClose} disabled={isConfirming}>
                        {cancelLabel}
                    </Button>
                    <button
                        type="button"
                        onClick={onConfirm}
                        disabled={isConfirming}
                        className={`flex w-full items-center justify-center gap-2 rounded-lg px-4 py-3 text-sm font-medium text-white shadow-theme-xs transition disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto ${confirmButtonClass}`}
                    >
                        {isConfirming && <Spinner size="sm" color="white" />}
                        {confirmLabel}
                    </button>
                </div>
            </div>
        </Modal>
    );
};
