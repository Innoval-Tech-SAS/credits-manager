import type { ApiClient } from '../types/api-client';

export type ClientFullSelectProps = {
    id?: string;
    selected: ApiClient | null;
    onSelect: (client: ApiClient | null) => void;
};
