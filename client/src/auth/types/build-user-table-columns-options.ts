import type { ApiUserListItem } from './api-user-list-item';

export type BuildUserTableColumnsOptions = {
    canEdit: boolean;
    canDelete: boolean;
    canChangePassword: boolean;
    onEdit: (row: ApiUserListItem) => void;
    onDelete: (row: ApiUserListItem) => void;
    onChangePassword: (row: ApiUserListItem) => void;
};
