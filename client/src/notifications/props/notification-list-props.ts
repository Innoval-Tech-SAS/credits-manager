import type { ApiNotification } from '../types/api-notification';

export type NotificationListProps = {
    notifications: ApiNotification[];
    emptyMessage: string;
    onSelect: (notification: ApiNotification) => void;
    onDownload: (notification: ApiNotification) => void;
    isDownloading: boolean;
};
