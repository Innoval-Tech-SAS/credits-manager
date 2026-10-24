/** Raw shape of a Laravel paginator JSON response. */
export type PaginatedResponse<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};
