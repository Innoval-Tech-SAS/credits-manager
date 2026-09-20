import type { ReactNode } from 'react';

export type ModuleListPageProps = {
    pageTitle: string;
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
