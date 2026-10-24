import type { ApiNotification } from './api-notification';

/** Raw shape returned by `GET /notificaciones`. */
export type ApiNotificationsResponse = {
    data: ApiNotification[];
    no_leidas: number;
};
