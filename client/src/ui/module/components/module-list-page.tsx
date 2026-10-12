import type { FC } from 'react';

import { Alert } from '@ui/alert/components/alert';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import type { ModuleListPageProps } from '../props/module-list-page-props';

/** El título va al encabezado fijo del layout (`PageBreadcrumb`); la tarjeta conserva el contador y las acciones. */
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
        <PageBreadcrumb pageTitle={pageTitle} />

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
                <p className="text-sm text-gray-500 dark:text-gray-400">{countLabel}</p>

                {toolbar && <div className="flex flex-row flex-wrap items-center gap-3 lg:justify-end">{toolbar}</div>}
            </div>

            {children}

            {pagination && <div className="mt-4">{pagination}</div>}
        </div>

        {modals}
    </div>
);
