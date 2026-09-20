import { useEffect, useState } from 'react';
import { useLocation, useNavigate } from 'react-router';

export function useLocationSuccessMessage<T extends { message?: string }>() {
    const location = useLocation();
    const navigate = useNavigate();
    const [successMessage, setSuccessMessage] = useState(() => (location.state as T | null)?.message ?? null);

    useEffect(() => {
        if (location.state) {
            navigate(location.pathname, { replace: true, state: null });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    return [successMessage, setSuccessMessage] as const;
}
