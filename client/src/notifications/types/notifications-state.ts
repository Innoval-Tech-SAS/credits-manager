import type { ApiNotification } from './api-notification';

export type NotificationsState = {
    items: ApiNotification[];
    unread: number;
};
