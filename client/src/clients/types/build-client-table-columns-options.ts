import type { ApiClient } from './api-client';

export type BuildClientTableColumnsOptions = {
    canEdit: boolean;
    canDelete: boolean;
    onEdit: (row: ApiClient) => void;
    onView: (row: ApiClient) => void;
    onDelete: (row: ApiClient) => void;
};
