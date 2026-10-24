import { useCallback, useRef, useState } from 'react';

import { API_PREFIX, API_ROOT } from '../constants/api';
import { SESSION_EXPIRED_EVENT } from '../constants/auth-events';
import type { SendRequestOptions } from '../types/send-request-options';
import { readCookie } from '../utils/read-cookie';
import { RequestValidationError } from '../utils/request-validation-error';

/** Marca un 401: `AuthProvider` ya reacciona vía `SESSION_EXPIRED_EVENT`, así que el
 * llamador no debe mostrar este mensaje como si fuera un error del formulario/página. */
class SessionExpiredError extends Error {}

export function useSendRequest() {
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const latestRequestId = useRef(0);

    const sendRequest = useCallback(async <T = unknown>(path: string, options: SendRequestOptions = {}): Promise<T> => {
        const requestId = ++latestRequestId.current;
        const method = options.method ?? 'GET';

        setIsLoading(true);
        setError(null);

        try {
            if (method !== 'GET') {
                await fetch(`${API_ROOT}/sanctum/csrf-cookie`, { credentials: 'include' });
            }

            const isFormData = options.body instanceof FormData;

            const headers = new Headers({ Accept: 'application/json' });
            if (options.body !== undefined && !isFormData) {
                headers.set('Content-Type', 'application/json');
            }

            const csrfToken = readCookie('XSRF-TOKEN');
            if (csrfToken) {
                headers.set('X-XSRF-TOKEN', csrfToken);
            }

            const response = await fetch(`${API_ROOT}${API_PREFIX}${path}`, {
                method,
                credentials: 'include',
                headers,
                body: isFormData ? (options.body as FormData) : options.body !== undefined ? JSON.stringify(options.body) : undefined,
            });

            if (response.status === 401) {
                window.dispatchEvent(new Event(SESSION_EXPIRED_EVENT));
                throw new SessionExpiredError('Unauthenticated.');
            }

            if (!response.ok) {
                const body = await response.json().catch(() => null);
                const fieldErrors = body?.errors as Record<string, string[]> | undefined;
                const firstFieldError = fieldErrors ? Object.values(fieldErrors)[0]?.[0] : undefined;
                if (fieldErrors && firstFieldError) {
                    throw new RequestValidationError(firstFieldError, fieldErrors);
                }
                throw new Error(firstFieldError ?? body?.message ?? 'Ocurrió un error inesperado.');
            }

            if (response.status === 204) {
                return undefined as T;
            }

            return (await response.json()) as T;
        } catch (caught) {
            const message = caught instanceof Error && !(caught instanceof TypeError)
                ? caught.message
                : 'Ocurrió un error inesperado.';

            if (!(caught instanceof SessionExpiredError) && requestId === latestRequestId.current) {
                setError(message);
            }
            throw caught;
        } finally {
            if (requestId === latestRequestId.current) {
                setIsLoading(false);
            }
        }
    }, []);

    return { sendRequest, isLoading, error };
}
