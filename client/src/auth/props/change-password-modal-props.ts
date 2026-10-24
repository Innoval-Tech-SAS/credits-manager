import type { ApiUserListItem } from '../types/api-user-list-item';

export type ChangePasswordModalProps = {
    user: ApiUserListItem;
    onClose: () => void;
    onSuccess: () => void;
};
