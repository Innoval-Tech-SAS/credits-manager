import type { ApiNotification } from '../types/api-notification';

export type NotificationDetailModalProps = {
    notification: ApiNotification | null;
    onClose: () => void;
};
