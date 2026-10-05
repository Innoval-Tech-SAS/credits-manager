import { useEffect, useState } from 'react';

import type { PaginatedResponse } from '../types/paginated-response';
import type { SortDirection } from '../types/sort-direction';
import { useSendRequest } from './use-send-request';

export function usePaginatedResource<T>(endpoint: string | null, filters: Record<string, string> = {}) {
    const { sendRequest, isLoading, error } = useSendRequest();

    const [items, setItems] = useState<T[]>([]);
    const [page, setPage] = useState(1);
    const [totalPages, setTotalPages] = useState(1);
    const [totalItems, setTotalItems] = useState(0);
    const [search, setSearch] = useState('');
    const [sortKey, setSortKey] = useState<string | null>(null);
    const [sortDirection, setSortDirection] = useState<SortDirection>('asc');
    const [refreshToken, setRefreshToken] = useState(0);

    useEffect(() => {
        if (!endpoint) {
            return;
        }

        let isCancelled = false;

        async function fetchPage() {
            const params = new URLSearchParams({ page: String(page), per_page: '10' });

            if (search) {
                params.set('search', search);
            }

            if (sortKey) {
                params.set('sort', sortKey);
                params.set('direccion', sortDirection);
            }

            for (const [key, value] of Object.entries(filters)) {
                if (value) {
                    params.set(key, value);
                }
            }

            const response = await sendRequest<PaginatedResponse<T>>(`${endpoint}?${params.toString()}`);

            if (!isCancelled) {
                setItems(response.data);
                setTotalPages(response.last_page);
                setTotalItems(response.total);
            }
        }

        void fetchPage();

        return () => {
            isCancelled = true;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [endpoint, page, search, sortKey, sortDirection, sendRequest, refreshToken, JSON.stringify(filters)]);

    const toggleSort = (key: string) => {
        if (sortKey === key) {
            setSortDirection((current) => (current === 'asc' ? 'desc' : 'asc'));
        } else {
            setSortKey(key);
            setSortDirection('asc');
        }
        setPage(1);
    };

    const updateSearch = (value: string) => {
        setSearch(value);
        setPage(1);
    };

    const refetch = () => {
        setRefreshToken((token) => token + 1);
    };

    return {
        items,
        isLoading,
        error,
        page,
        setPage,
        totalPages,
        totalItems,
        search,
        setSearch: updateSearch,
        sortKey,
        sortDirection,
        toggleSort,
        refetch,
    };
}
