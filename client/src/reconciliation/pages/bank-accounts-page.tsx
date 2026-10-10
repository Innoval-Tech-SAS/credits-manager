import type { FC } from 'react';
import { useNavigate } from 'react-router';
import { Eye, Landmark } from 'lucide-react';

import { useLocationSuccessMessage } from '../../shared/hooks/use-location-success-message';
import { useResource } from '../../shared/hooks/use-resource';
import { usePermission } from '../../auth/hooks/use-permission';
import { Button } from '@ui/button/components/button';
import { ModuleListPage } from '@ui/module/components/module-list-page';
import { DataTable } from '@ui/table/components/data-table';
import { BANK_ACCOUNT_STATUS_BADGE_CLASSES } from '../constants/bank-account-status-badge-classes';
import { BANK_LABELS, BANK_ACCOUNT_TYPE_LABELS } from '../constants/bank-options';
import type { BankAccountsListLocationState } from '../types/bank-accounts-list-location-state';
import type { ApiBankAccount } from '../types/api-bank-account';

export const BankAccountsPage: FC = () => {
    const navigate = useNavigate();

    const canCreate = usePermission('conciliacion', 'configurar');
    const [successMessage, setSuccessMessage] = useLocationSuccessMessage<BankAccountsListLocationState>();
    const { data, isLoading } = useResource<{ cuentas_bancarias: ApiBankAccount[] }>('/conciliacion/cuentas-bancarias');
    const cuentas = data?.cuentas_bancarias ?? [];

    return (
        <ModuleListPage
            pageTitle="Cuentas bancarias"
            successMessage={successMessage}
            onDismissSuccess={() => setSuccessMessage(null)}
            countLabel={`${cuentas.length} ${cuentas.length === 1 ? 'cuenta registrada' : 'cuentas registradas'}`}
            toolbar={
                canCreate && (
                    <Button collapseBelow="md" startIcon={<Landmark className="size-4" />} onClick={() => navigate('/conciliacion/cuentas-bancarias/crear')}>
                        Crear cuenta bancaria
                    </Button>
                )
            }
        >
            <DataTable
                columns={[
                    { key: 'banco', label: 'Banco', render: (row) => BANK_LABELS[row.banco] },
                    { key: 'numero_cuenta', label: 'Número de cuenta' },
                    { key: 'tipo_cuenta', label: 'Tipo', render: (row) => BANK_ACCOUNT_TYPE_LABELS[row.tipo_cuenta] },
                    { key: 'alias', label: 'Alias', render: (row) => row.alias ?? '—' },
                    { key: 'plan_cuenta', label: 'Cuenta PUC', render: (row) => (row.plan_cuenta ? `${row.plan_cuenta.codigo} - ${row.plan_cuenta.nombre}` : '—') },
                    {
                        key: 'activa',
                        label: 'Estado',
                        render: (row) => (
                            <span
                                className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${BANK_ACCOUNT_STATUS_BADGE_CLASSES[row.activa ? 'activa' : 'inactiva']}`}
                            >
                                {row.activa ? 'Activa' : 'Inactiva'}
                            </span>
                        ),
                    },
                    {
                        key: 'acciones',
                        label: 'Extractos',
                        render: (row) => (
                            <button
                                type="button"
                                onClick={() => navigate(`/conciliacion/cuentas-bancarias/${row.id}/extractos`)}
                                className="text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white/90"
                            >
                                <Eye className="size-5" />
                            </button>
                        ),
                    },
                ]}
                rows={cuentas}
                getRowKey={(row) => row.id}
                loading={isLoading}
                emptyMessage="Sin cuentas bancarias registradas."
            />
        </ModuleListPage>
    );
};
