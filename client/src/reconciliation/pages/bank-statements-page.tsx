import type { FC } from 'react';
import { useState } from 'react';
import { useNavigate, useParams } from 'react-router';
import { Upload } from 'lucide-react';

import { useResource } from '../../shared/hooks/use-resource';
import { usePermission } from '../../auth/hooks/use-permission';
import { formatMoney } from '../../shared/utils/format-money';
import { Button } from '@ui/button/components/button';
import { ModuleListPage } from '@ui/module/components/module-list-page';
import { DataTable } from '@ui/table/components/data-table';
import { UploadBankStatementModal } from '../components/upload-bank-statement-modal';
import { BANK_STATEMENT_STATUS_BADGE_CLASSES, BANK_STATEMENT_STATUS_LABELS } from '../constants/bank-statement-status';
import type { ApiBankAccount } from '../types/api-bank-account';
import type { ApiBankStatement } from '../types/api-bank-statement';

export const BankStatementsPage: FC = () => {
    const { id } = useParams<{ id: string }>();
    const cuentaBancariaId = Number(id);
    const navigate = useNavigate();

    const canCargar = usePermission('conciliacion', 'cargar_extracto');
    const [successMessage, setSuccessMessage] = useState<string | null>(null);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const [isUploadOpen, setIsUploadOpen] = useState(false);

    const { data: cuentasData } = useResource<{ cuentas_bancarias: ApiBankAccount[] }>('/conciliacion/cuentas-bancarias');
    const cuenta = cuentasData?.cuentas_bancarias.find((item) => item.id === cuentaBancariaId) ?? null;

    const { data, isLoading, refetch } = useResource<{ extractos: ApiBankStatement[] }>(`/conciliacion/cuentas-bancarias/${cuentaBancariaId}/extractos`);
    const extractos = data?.extractos ?? [];

    const pageTitle = cuenta ? `Extractos — ${cuenta.alias ?? cuenta.numero_cuenta}` : 'Extractos';

    return (
            <ModuleListPage
                pageTitle={pageTitle}
                successMessage={successMessage}
                onDismissSuccess={() => setSuccessMessage(null)}
                errorMessage={errorMessage}
                onDismissError={() => setErrorMessage(null)}
                countLabel={`${extractos.length} ${extractos.length === 1 ? 'extracto cargado' : 'extractos cargados'}`}
                toolbar={
                    canCargar && (
                        <Button collapseBelow="md" startIcon={<Upload className="size-4" />} onClick={() => setIsUploadOpen(true)}>
                            Cargar extracto
                        </Button>
                    )
                }
                modals={
                    <UploadBankStatementModal
                        isOpen={isUploadOpen}
                        cuentaBancariaId={cuentaBancariaId}
                        onClose={() => setIsUploadOpen(false)}
                        onUploaded={(mensaje) => {
                            setIsUploadOpen(false);
                            setSuccessMessage(mensaje);
                            refetch();
                        }}
                        onError={(mensaje) => {
                            setIsUploadOpen(false);
                            setErrorMessage(mensaje);
                        }}
                    />
                }
            >
                <DataTable
                    columns={[
                        { key: 'fecha_desde', label: 'Desde' },
                        { key: 'fecha_hasta', label: 'Hasta' },
                        { key: 'saldo_anterior', label: 'Saldo anterior', render: (row) => formatMoney(row.saldo_anterior) },
                        { key: 'saldo_actual', label: 'Saldo actual', render: (row) => formatMoney(row.saldo_actual) },
                        {
                            key: 'estado',
                            label: 'Estado',
                            render: (row) => (
                                <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${BANK_STATEMENT_STATUS_BADGE_CLASSES[row.estado]}`}>
                                    {BANK_STATEMENT_STATUS_LABELS[row.estado]}
                                </span>
                            ),
                        },
                        { key: 'motivo_rechazo', label: 'Motivo', render: (row) => row.motivo_rechazo ?? '—' },
                        {
                            key: 'acciones',
                            label: 'Acción',
                            render: (row) =>
                                row.estado === 'cargado' && (
                                    <Button size="sm" variant="outline" onClick={() => navigate(`/conciliacion/extractos/${row.id}/conciliar`)}>
                                        Conciliar
                                    </Button>
                                ),
                        },
                    ]}
                    rows={extractos}
                    getRowKey={(row) => row.id}
                    loading={isLoading}
                    emptyMessage="Sin extractos cargados para esta cuenta."
                />
            </ModuleListPage>
    );
};
