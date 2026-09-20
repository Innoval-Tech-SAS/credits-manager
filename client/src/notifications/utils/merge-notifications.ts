import type { ApiNotification } from '../types/api-notification';
import type { NotificationsState } from '../types/notifications-state';

export const MAX_NOTIFICATIONS = 20;

/**
 * Une la carga inicial con lo que ya llegó por WebSocket mientras cargaba. La del servidor manda si un
 * mismo id aparece en ambas; lo que solo llegó por el socket y sigue sin leer se suma al contador.
 */
export function mergeNotifications(current: NotificationsState, fetched: ApiNotification[], serverUnread: number): NotificationsState {
    const fetchedIds = new Set(fetched.map((notification) => notification.id));
    const onlyFromSocket = current.items.filter((notification) => !fetchedIds.has(notification.id));

    return {
        items: [...onlyFromSocket, ...fetched].sort((a, b) => b.id - a.id).slice(0, MAX_NOTIFICATIONS),
        unread: serverUnread + onlyFromSocket.filter((notification) => !notification.leida_en).length,
    };
}

export function addNotification(current: NotificationsState, incoming: ApiNotification): NotificationsState {
    if (current.items.some((notification) => notification.id === incoming.id)) {
        return current;
    }

    return {
        items: [incoming, ...current.items].slice(0, MAX_NOTIFICATIONS),
        unread: current.unread + (!incoming.leida_en ? 1 : 0),
    };
}
