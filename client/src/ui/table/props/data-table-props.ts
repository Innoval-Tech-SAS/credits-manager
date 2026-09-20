import type { ReactNode } from 'react';

export type DataTableColumn<T> = {
    key: string;
    label: string;
    sortable?: boolean;
    render?: (row: T) => ReactNode;
};

export type DataTableProps<T> = {
    columns: DataTableColumn<T>[];
    rows: T[];
    getRowKey: (row: T) => string | number;
    sortKey?: string | null;
    sortDirection?: 'asc' | 'desc';
    onSortChange?: (key: string) => void;
    loading?: boolean;
    loadingMessage?: string;
    emptyMessage?: string;
    /** Tailwind max-height class (e.g. `max-h-[65vh]`) — when set, only the table body scrolls (with a sticky header) instead of the whole page. */
    maxHeightClassName?: string;
};
