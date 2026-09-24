import type { FC } from 'react';
import { Link, useLocation } from 'react-router';

import { ChevronRight, Home } from 'lucide-react';
import { usePageHeader } from '@ui/breadcrumb/hooks/use-page-header';
import { GoBackButton } from '@ui/go-back/components/go-back-button';
import { Tooltip } from '@ui/tooltip/components/tooltip';
import { findNavBreadcrumb } from '../utils/find-nav-breadcrumb';

/** La ruta de migas va solo con iconos (el nombre sale en el tooltip): el título de la página ya dice dónde estás y así nunca se parte en móvil. */
export const AppPageHeader: FC = () => {
    const { title, hideTitle } = usePageHeader();
    const location = useLocation();

    if (!title) {
        return null;
    }

    const trail = findNavBreadcrumb(location.pathname);

    return (
        <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div className="flex min-w-0 items-center gap-2">
                <GoBackButton />
                {!hideTitle && <h2 className="text-xl font-semibold text-gray-800 dark:text-white/90">{title}</h2>}
            </div>
            <nav aria-label="Ruta de navegación">
                <ol className="flex items-center gap-1.5">
                    <li className="flex items-center gap-1.5">
                        <Tooltip label="Inicio">
                            <Link to="/" aria-label="Inicio" className="inline-flex size-6 items-center justify-center text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white/90">
                                <Home className="size-4" />
                            </Link>
                        </Tooltip>
                        {trail.length > 0 && <ChevronRight className="size-4 text-gray-400" />}
                    </li>
                    {trail.map((crumb, index) => {
                        const isLast = index === trail.length - 1;
                        const Icon = crumb.icon;

                        return (
                            <li key={crumb.name} className="flex items-center gap-1.5">
                                <Tooltip label={crumb.name}>
                                    <span
                                        tabIndex={0}
                                        aria-label={crumb.name}
                                        aria-current={isLast ? 'page' : undefined}
                                        className={`inline-flex min-h-6 items-center text-sm ${
                                            isLast ? 'text-gray-800 dark:text-white/90' : 'text-gray-500 dark:text-gray-400'
                                        }`}
                                    >
                                        {Icon ? <Icon className="size-4" /> : crumb.name}
                                    </span>
                                </Tooltip>
                                {!isLast && <ChevronRight className="size-4 text-gray-400" />}
                            </li>
                        );
                    })}
                </ol>
            </nav>
        </div>
    );
};
