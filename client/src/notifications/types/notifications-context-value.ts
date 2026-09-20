import type { ApiNotification } from './api-notification';

export type NotificationsContextValue = {
    notifications: ApiNotification[];
    unreadCount: number;
    isLoading: boolean;
    markAsRead: (id: number) => Promise<void>;
    markAllAsRead: () => Promise<void>;
};
