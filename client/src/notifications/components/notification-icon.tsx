import type { FC } from 'react';

import { NOTIFICATION_TYPE_ICONS } from '../constants/notification-type-icons';
import type { NotificationIconProps } from '../props/notification-icon-props';

export const NotificationIcon: FC<NotificationIconProps> = ({ tipo }) => {
    const { Icon, className } = NOTIFICATION_TYPE_ICONS[tipo];

    return <Icon className={`size-5 ${className}`} />;
};
