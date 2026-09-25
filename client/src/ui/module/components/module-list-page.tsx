import type { FC } from 'react';

import { Alert } from '@ui/alert/components/alert';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import type { ModuleListPageProps } from '../props/module-list-page-props';

export const ModuleListPage: FC<ModuleListPageProps> = ({
    pageTitle,
    successMessage,
    onDismissSuccess,
    errorMessage,
    onDismissError,
    countLabel,
    toolbar,
    pagination,
    modals,
    children,
}) => (
    <div className="flex min-h-full flex-col pb-6">
        <PageBreadcrumb pageTitle={pageTitle} hideTitle />

        {successMessage && (
            <div className="mb-6">
                <Alert variant="success" title="Listo" message={successMessage} onDismiss={onDismissSuccess} />
            </div>
        )}

        {errorMessage && (
            <div className="mb-6">
                <Alert variant="danger" title="Error" message={errorMessage} onDismiss={onDismissError} />
            </div>
        )}

        <div className="flex-1 rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
            <div className="mb-6 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">{pageTitle}</h2>
                    <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">{countLabel}</p>
                </div>

                {toolbar && <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center lg:justify-end">{toolbar}</div>}
            </div>

            {children}

            {pagination && <div className="mt-4">{pagination}</div>}
        </div>

        {modals}
    </div>
);
