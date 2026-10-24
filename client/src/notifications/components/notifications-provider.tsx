import type { FC } from 'react';
import { useCallback, useEffect, useMemo, useState } from 'react';

import { useAuth } from '../../auth/hooks/use-auth';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import type { WithChildrenProps } from '../../shared/props/with-children-props';
import { NotificationsContext } from '../hooks/use-notifications';
import type { ApiNotification } from '../types/api-notification';
import type { ApiNotificationsResponse } from '../types/api-notifications-response';
import type { NotificationsState } from '../types/notifications-state';
import { createEcho } from '../utils/create-echo';
import { addNotification, mergeNotifications } from '../utils/merge-notifications';

const EMPTY_STATE: NotificationsState = { items: [], unread: 0 };

/**
 * Carga la lista una sola vez al iniciar sesión y, desde ahí, solo recibe empujes por WebSocket
 * (canal privado del usuario): no hay consultas periódicas.
 */
export const NotificationsProvider: FC<WithChildrenProps> = ({ children }) => {
    const { user } = useAuth();
    const { sendRequest, isLoading } = useSendRequest();
    const [state, setState] = useState<NotificationsState>(EMPTY_STATE);

    const userId = user?.id ?? null;

    useEffect(() => {
        if (userId === null) {
            return;
        }

        let isCancelled = false;
        const channelName = `usuarios.${userId}`;
        const echo = createEcho();

        echo.private(channelName).listen('.notificacion.creada', (event: { notificacion: ApiNotification }) => {
            setState((current) => addNotification(current, event.notificacion));
        });

        sendRequest<ApiNotificationsResponse>('/notificaciones')
            .then((response) => {
                if (!isCancelled) {
                    setState((current) => mergeNotifications(current, response.data, response.no_leidas));
                }
            })
            .catch(() => {});

        return () => {
            isCancelled = true;
            echo.leave(channelName);
            echo.disconnect();
            setState(EMPTY_STATE);
        };
    }, [userId, sendRequest]);

    const markAsRead = useCallback(
        async (id: number) => {
            const response = await sendRequest<{ notificacion: ApiNotification }>(`/notificaciones/${id}/leer`, { method: 'POST' });

            setState((current) => {
                const previous = current.items.find((notification) => notification.id === id);

                return {
                    items: current.items.map((notification) => (notification.id === id ? response.notificacion : notification)),
                    unread: previous && !previous.leida_en ? Math.max(0, current.unread - 1) : current.unread,
                };
            });
        },
        [sendRequest],
    );

    const markAllAsRead = useCallback(async () => {
        await sendRequest('/notificaciones/leer-todas', { method: 'POST' });

        const readAt = new Date().toISOString();

        setState((current) => ({
            items: current.items.map((notification) => ({ ...notification, leida_en: notification.leida_en ?? readAt })),
            unread: 0,
        }));
    }, [sendRequest]);

    const value = useMemo(
        () => ({ notifications: state.items, unreadCount: state.unread, isLoading, markAsRead, markAllAsRead }),
        [state, isLoading, markAsRead, markAllAsRead],
    );

    return <NotificationsContext.Provider value={value}>{children}</NotificationsContext.Provider>;
};
