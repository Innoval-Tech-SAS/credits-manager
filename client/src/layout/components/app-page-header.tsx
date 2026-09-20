import type { FC } from 'react';
import { Link, useLocation } from 'react-router';

import { ChevronRight, Home } from 'lucide-react';
import { usePageHeader } from '@ui/breadcrumb/hooks/use-page-header';
import { GoBackButton } from '@ui/go-back/components/go-back-button';
import { findNavBreadcrumb } from '../utils/find-nav-breadcrumb';

export const AppPageHeader: FC = () => {
    const { title, hideTitle } = usePageHeader();
    const location = useLocation();

    if (!title) {
        return null;
    }

    const trail = findNavBreadcrumb(location.pathname);

    return (
        <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div className="flex items-center gap-3">
                <GoBackButton />
                {!hideTitle && <h2 className="text-xl font-semibold text-gray-800 dark:text-white/90">{title}</h2>}
            </div>
            <nav>
                <ol className="flex items-center gap-1.5">
                    <li>
                        <Link
                            to="/"
                            className="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400"
                        >
                            <Home className="size-4" />
                            Inicio
                            {trail.length > 0 && <ChevronRight className="size-4 text-current" />}
                        </Link>
                    </li>
                    {trail.map((crumb, index) => {
                        const isLast = index === trail.length - 1;
                        const Icon = crumb.icon;

                        return (
                            <li
                                key={crumb.name}
                                className={`flex items-center gap-1.5 text-sm ${
                                    isLast ? 'text-gray-800 dark:text-white/90' : 'text-gray-500 dark:text-gray-400'
                                }`}
                            >
                                {Icon && <Icon className="size-4" />}
                                {crumb.name}
                                {!isLast && <ChevronRight className="size-4 text-current" />}
                            </li>
                        );
                    })}
                </ol>
            </nav>
        </div>
    );
};
