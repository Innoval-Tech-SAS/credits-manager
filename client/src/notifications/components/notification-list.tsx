import type { FC } from 'react';

import { Download } from 'lucide-react';
import { Button } from '@ui/button/components/button';
import { formatDateTimeEs } from '../../shared/utils/format-datetime-es';
import { NotificationIcon } from './notification-icon';
import type { NotificationListProps } from '../props/notification-list-props';

export const NotificationList: FC<NotificationListProps> = ({ notifications, emptyMessage, onSelect, onDownload, isDownloading }) => {
    if (notifications.length === 0) {
        return <p className="py-6 text-center text-sm text-gray-400 dark:text-gray-500">{emptyMessage}</p>;
    }

    return (
        <ul className="divide-y divide-gray-100 dark:divide-gray-800">
            {notifications.map((notification) => (
                <li key={notification.id}>
                    <button
                        type="button"
                        onClick={() => onSelect(notification)}
                        className="flex w-full gap-3 rounded-lg px-2 py-4 text-left hover:bg-gray-50 dark:hover:bg-white/5"
                    >
                        <span className="mt-0.5 shrink-0">
                            <NotificationIcon tipo={notification.tipo} />
                        </span>

                        <div className="min-w-0 flex-1">
                            <p className="flex items-center gap-2 text-sm font-medium text-gray-800 dark:text-white/90">
                                {notification.titulo}
                                {!notification.leida_en && <span className="size-2 shrink-0 rounded-full bg-brand-500" aria-label="Sin leer" />}
                            </p>
                            <p className="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{notification.mensaje}</p>
                            <p className="mt-1 text-theme-xs text-gray-400 dark:text-gray-500">{formatDateTimeEs(notification.creado_en)}</p>

                            {notification.tipo === 'exportacion_lista' && notification.datos?.exportacion_id !== undefined && (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    disabled={isDownloading}
                                    startIcon={<Download className="size-3.5" />}
                                    onClick={(event) => {
                                        event.stopPropagation();
                                        onDownload(notification);
                                    }}
                                    className="mt-2"
                                >
                                    {isDownloading ? 'Descargando…' : 'Descargar'}
                                </Button>
                            )}
                        </div>
                    </button>
                </li>
            ))}
        </ul>
    );
};
