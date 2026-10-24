import { createContext, useContext } from 'react';

import type { NotificationsContextValue } from '../types/notifications-context-value';

export const NotificationsContext = createContext<NotificationsContextValue | undefined>(undefined);

export const useNotifications = (): NotificationsContextValue => {
    const context = useContext(NotificationsContext);

    if (!context) {
        throw new Error('useNotifications must be used within a NotificationsProvider');
    }

    return context;
};
