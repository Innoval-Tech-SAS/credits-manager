export type Method = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

export type SendRequestOptions = {
    method?: Method;
    body?: unknown;
};
