import type { FC } from 'react';
import { useLocation, useNavigate } from 'react-router';

import { ChevronLeft } from 'lucide-react';

export const GoBackButton: FC = () => {
    const navigate = useNavigate();
    const location = useLocation();

    if (location.pathname === '/') {
        return null;
    }

    return (
        <button
            type="button"
            onClick={() => navigate(-1)}
            className="inline-flex w-fit items-center gap-1.5 text-sm font-medium text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white/90"
        >
            <ChevronLeft className="size-4" />
            Volver
        </button>
    );
};
