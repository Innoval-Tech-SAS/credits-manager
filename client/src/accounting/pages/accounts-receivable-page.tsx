import type { FC } from 'react';

import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { ThirdPartyDocumentsTable } from '../components/third-party-documents-table';

export const AccountsReceivablePage: FC = () => (
    <div className="flex min-h-full flex-col gap-6 pb-6">
        <PageBreadcrumb pageTitle="Cuentas por Cobrar" />
        <ThirdPartyDocumentsTable naturaleza="por_cobrar" />
    </div>
);
