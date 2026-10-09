import type { FC } from 'react';

import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { ThirdPartyLedgerTable } from '../components/third-party-ledger-table';

export const AccountsReceivablePage: FC = () => (
    <div className="flex min-h-full flex-col gap-6 pb-6">
        <PageBreadcrumb pageTitle="Cuentas por Cobrar" />
        <ThirdPartyLedgerTable naturaleza="por_cobrar" />
    </div>
);
