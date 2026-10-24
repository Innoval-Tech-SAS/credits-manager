import type { FC } from 'react';

import { X } from 'lucide-react';
import { MODAL_VARIANT_STYLES } from '@ui/modal/constants/modal-variant-styles';
import type { AlertProps } from '../props/alert-props';

export const Alert: FC<AlertProps> = ({ variant, title, message, onDismiss }) => {
    const { iconWrapperClass, iconClass, Icon } = MODAL_VARIANT_STYLES[variant];

    return (
        <div className={`flex items-start gap-3 rounded-xl p-4 ${iconWrapperClass}`}>
            <span className={`mt-0.5 ${iconClass}`}>
                <Icon className="size-5" />
            </span>

            <div className="flex-1">
                <h4 className="mb-1 text-sm font-semibold text-gray-800 dark:text-white/90">{title}</h4>
                <p className="text-sm text-gray-500 dark:text-gray-400">{message}</p>
            </div>

            {onDismiss && (
                <button
                    type="button"
                    onClick={onDismiss}
                    aria-label="Cerrar"
                    className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                >
                    <X className="size-4" />
                </button>
            )}
        </div>
    );
};
