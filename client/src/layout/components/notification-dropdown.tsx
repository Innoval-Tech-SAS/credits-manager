import type { FC } from 'react';
import { useState } from 'react';

import { Dropdown } from '@ui/dropdown/components/dropdown';
import { Spinner } from '@ui/spinner/components/spinner';
import { AlertCircle, Bell, CircleCheck, Download } from 'lucide-react';
import { useNotifications } from '../../notifications/hooks/use-notifications';
import type { ApiNotification } from '../../notifications/types/api-notification';
import { useDownloadExport } from '../../shared/hooks/use-download-export';
import { formatDateTimeEs } from '../../shared/utils/format-datetime-es';

export const NotificationDropdown: FC = () => {
    const [isOpen, setIsOpen] = useState(false);
    const { notifications, unreadCount, isLoading, markAsRead, markAllAsRead } = useNotifications();
    const { download, isDownloading, error: downloadError } = useDownloadExport();

    const toggleDropdown = () => setIsOpen((previous) => !previous);
    const closeDropdown = () => setIsOpen(false);

    const handleRead = (notification: ApiNotification) => {
        if (!notification.leida_en) {
            void markAsRead(notification.id).catch(() => {});
        }
    };

    const handleDownload = async (notification: ApiNotification) => {
        const exportId = notification.datos?.exportacion_id;

        if (exportId === undefined) {
            return;
        }

        await download(exportId);
        handleRead(notification);
    };

    return (
        <div className="relative">
            <button
                type="button"
                onClick={toggleDropdown}
                aria-label={unreadCount > 0 ? `Notificaciones (${unreadCount} sin leer)` : 'Notificaciones'}
                className="dropdown-toggle relative flex h-11 w-11 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
            >
                {unreadCount > 0 && (
                    <span className="absolute -top-1 -right-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-error-500 px-1 text-[10px] font-semibold text-white">
                        {unreadCount > 9 ? '9+' : unreadCount}
                    </span>
                )}
                <Bell className="size-5 fill-current" />
            </button>

            <Dropdown isOpen={isOpen} onClose={closeDropdown} className="fixed! top-20 right-4! left-4! mt-0 flex w-auto flex-col p-4 sm:absolute! sm:top-auto sm:right-0! sm:left-auto! sm:mt-4.25 sm:w-80">
                <div className="mb-3 flex items-center justify-between gap-2 border-b border-gray-100 pb-3 dark:border-gray-700">
                    <h5 className="text-sm font-semibold text-gray-800 dark:text-gray-200">Notificaciones</h5>

                    {unreadCount > 0 && (
                        <button
                            type="button"
                            onClick={() => void markAllAsRead().catch(() => {})}
                            className="text-xs font-medium text-brand-500 hover:text-brand-600 dark:text-brand-400"
                        >
                            Marcar todas como leídas
                        </button>
                    )}
                </div>

                {downloadError && (
                    <p className="mb-2 flex items-start gap-1.5 rounded-lg bg-error-50 px-3 py-2 text-xs text-error-600 dark:bg-error-500/10 dark:text-error-400">
                        <AlertCircle className="mt-0.5 size-3.5 shrink-0" />
                        {downloadError}
                    </p>
                )}

                {isLoading && notifications.length === 0 ? (
                    <div className="flex items-center justify-center gap-2 py-6 text-sm text-gray-400 dark:text-gray-500">
                        <Spinner size="sm" />
                        Cargando…
                    </div>
                ) : notifications.length === 0 ? (
                    <p className="py-6 text-center text-sm text-gray-400 dark:text-gray-500">No tienes notificaciones.</p>
                ) : (
                    <ul className="custom-scrollbar flex max-h-96 flex-col gap-1 overflow-y-auto">
                        {notifications.map((notification) => (
                            <li key={notification.id}>
                                <div
                                    onClick={() => handleRead(notification)}
                                    className="flex cursor-default gap-3 rounded-lg px-3 py-2.5 hover:bg-gray-50 dark:hover:bg-white/5"
                                >
                                    <span className="mt-0.5 shrink-0">
                                        {notification.tipo === 'exportacion_lista' ? (
                                            <CircleCheck className="size-5 text-success-500" />
                                        ) : (
                                            <AlertCircle className="size-5 text-error-500" />
                                        )}
                                    </span>

                                    <div className="min-w-0 flex-1">
                                        <p className="flex items-center gap-2 text-sm font-medium text-gray-800 dark:text-white/90">
                                            {notification.titulo}
                                            {!notification.leida_en && (
                                                <span className="size-2 shrink-0 rounded-full bg-brand-500" aria-label="Sin leer" />
                                            )}
                                        </p>
                                        <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{notification.mensaje}</p>
                                        <p className="mt-1 text-[11px] text-gray-400 dark:text-gray-500">{formatDateTimeEs(notification.creado_en)}</p>

                                        {notification.tipo === 'exportacion_lista' && notification.datos?.exportacion_id !== undefined && (
                                            <button
                                                type="button"
                                                disabled={isDownloading}
                                                onClick={(event) => {
                                                    event.stopPropagation();
                                                    void handleDownload(notification);
                                                }}
                                                className="mt-2 inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
                                            >
                                                <Download className="size-3.5" />
                                                {isDownloading ? 'Descargando…' : 'Descargar'}
                                            </button>
                                        )}
                                    </div>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </Dropdown>
        </div>
    );
};
