import { useEffect, useState } from 'react';

import type { PaginatedResponse } from '../types/paginated-response';
import { useSendRequest } from './use-send-request';

export function useTypeahead<T>(endpoint: string) {
    const { sendRequest, isLoading } = useSendRequest();

    const [query, setQuery] = useState('');
    const [isOpen, setIsOpen] = useState(false);
    const [results, setResults] = useState<T[]>([]);

    useEffect(() => {
        if (!isOpen) {
            return;
        }

        let isCancelled = false;

        const timeout = setTimeout(() => {
            const params = new URLSearchParams({ per_page: '8', page: '1' });
            if (query) {
                params.set('search', query);
            }

            sendRequest<PaginatedResponse<T>>(`${endpoint}?${params.toString()}`).then((response) => {
                if (!isCancelled) {
                    setResults(response.data);
                }
            });
        }, 300);

        return () => {
            isCancelled = true;
            clearTimeout(timeout);
        };
    }, [query, isOpen, endpoint, sendRequest]);

    const reset = () => {
        setQuery('');
        setIsOpen(false);
    };

    return { query, setQuery, isOpen, setIsOpen, results, isLoading, reset };
}
