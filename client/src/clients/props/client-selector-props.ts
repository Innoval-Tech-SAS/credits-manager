import type { ApiClient } from '../types/api-client';

export type ClientSelectorProps = {
    selected: ApiClient | null;
    onSelect: (client: ApiClient | null) => void;
};
