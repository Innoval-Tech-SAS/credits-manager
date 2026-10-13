import type { ReactNode } from 'react';

import type { BreadcrumbItem } from '@ui/breadcrumb/types/breadcrumb-item';

export type ModuleListPageProps = {
    pageTitle: string;
    /** Override de la ruta de migas cuando la página no tiene entrada en el sidebar. Por defecto sale del sidebar. */
    breadcrumbTrail?: BreadcrumbItem[];
    successMessage?: string | null;
    onDismissSuccess?: () => void;
    errorMessage?: string | null;
    onDismissError?: () => void;
    countLabel: string;
    toolbar?: ReactNode;
    pagination?: ReactNode;
    modals?: ReactNode;
    children: ReactNode;
};
