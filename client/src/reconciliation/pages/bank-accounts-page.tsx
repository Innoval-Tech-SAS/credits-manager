import type { FC } from 'react';
import { useNavigate } from 'react-router';
import { Eye, Landmark } from 'lucide-react';

import { useLocationSuccessMessage } from '../../shared/hooks/use-location-success-message';
import { useResource } from '../../shared/hooks/use-resource';
import { usePermission } from '../../auth/hooks/use-permission';
import { ActionButton } from '@ui/action-button/components/action-button';
import { CreateButton } from '@ui/button/components/create-button';
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
                    <CreateButton label="cuenta bancaria" icon={<Landmark className="size-4" />} onClick={() => navigate('/conciliacion/cuentas-bancarias/crear')} />
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
                            <ActionButton
                                icon={<Eye className="size-5" />}
                                label="Ver extractos"
                                onClick={() => navigate(`/conciliacion/cuentas-bancarias/${row.id}/extractos`)}
                            />
                        ),
                    },
                ]}
                rows={cuentas}
                getRowKey={(row) => row.id}
                loading={isLoading}
                emptyMessage="Sin cuentas bancarias registradas."
                maxHeightClassName="max-h-[65vh]"
            />
        </ModuleListPage>
    );
};
