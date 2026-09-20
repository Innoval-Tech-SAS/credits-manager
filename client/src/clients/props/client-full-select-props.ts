import type { ApiClient } from '../types/api-client';

export type ClientFullSelectProps = {
    selected: ApiClient | null;
    onSelect: (client: ApiClient | null) => void;
};
