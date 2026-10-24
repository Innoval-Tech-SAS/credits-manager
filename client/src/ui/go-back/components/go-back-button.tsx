import type { FC } from 'react';
import { useLocation, useNavigate } from 'react-router';

import { ChevronLeft } from 'lucide-react';
import { Tooltip } from '@ui/tooltip/components/tooltip';

export const GoBackButton: FC = () => {
    const navigate = useNavigate();
    const location = useLocation();

    if (location.pathname === '/') {
        return null;
    }

    return (
        <Tooltip label="Volver">
            <button
                type="button"
                aria-label="Volver"
                onClick={() => navigate(-1)}
                className="inline-flex size-10 shrink-0 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white/90"
            >
                <ChevronLeft className="size-5" />
            </button>
        </Tooltip>
    );
};
