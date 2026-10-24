import type { ApiClient } from '../types/api-client';

export type ClientInfoCardProps = {
    cliente: ApiClient;
    canEdit: boolean;
    onEdit: () => void;
};
