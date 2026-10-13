import type { FC } from 'react';

import { Button } from '@ui/button/components/button';
import { Modal } from '@ui/modal/components/modal';
import { formatDateTimeEs } from '../../shared/utils/format-datetime-es';
import { NOTIFICATION_TYPE_LABELS } from '../constants/notification-type-labels';
import { NotificationIcon } from './notification-icon';
import type { NotificationDetailModalProps } from '../props/notification-detail-modal-props';

/** Vista genérica para una notificación que no tiene un recurso al cual navegar (ej. una exportación fallida). */
export const NotificationDetailModal: FC<NotificationDetailModalProps> = ({ notification, onClose }) => (
    <Modal isOpen={notification !== null} onClose={onClose} size="sm">
        {notification && (
            <div className="flex flex-col gap-4">
                <div className="flex items-start gap-3">
                    <span className="mt-0.5 shrink-0">
                        <NotificationIcon tipo={notification.tipo} />
                    </span>
                    <div className="min-w-0">
                        <p className="text-theme-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">
                            {NOTIFICATION_TYPE_LABELS[notification.tipo]}
                        </p>
                        <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">{notification.titulo}</h4>
                    </div>
                </div>

                <p className="text-sm text-gray-600 dark:text-gray-300">{notification.mensaje}</p>

                <p className="text-theme-xs text-gray-400 dark:text-gray-500">{formatDateTimeEs(notification.creado_en)}</p>

                <div className="flex justify-end border-t border-gray-100 pt-4 dark:border-gray-800">
                    <Button variant="outline" onClick={onClose}>
                        Cerrar
                    </Button>
                </div>
            </div>
        )}
    </Modal>
);
