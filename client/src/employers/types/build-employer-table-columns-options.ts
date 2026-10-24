import type { ApiEmployer } from './api-employer';

export type BuildEmployerTableColumnsOptions = {
    canEdit: boolean;
    canDelete: boolean;
    onEdit: (row: ApiEmployer) => void;
    onDelete: (row: ApiEmployer) => void;
};
