import type { FC } from 'react';
import { useParams } from 'react-router';

import { useResource } from '../../shared/hooks/use-resource';
import { useCompany } from '../../company/hooks/use-company';
import { Button } from '@ui/button/components/button';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { Spinner } from '@ui/spinner/components/spinner';
import { AccountStatementHeader } from '../components/account-statement-header';
import { AccountStatementInstallmentsTable } from '../components/account-statement-installments-table';
import { AccountStatementPaymentsTable } from '../components/account-statement-payments-table';
import type { ApiAccountStatement } from '../types/api-account-statement';

export const AccountStatementPage: FC = () => {
    const { id } = useParams<{ id: string }>();
    const { company } = useCompany();

    const { data: estadoCuenta, error } = useResource<ApiAccountStatement>(`/creditos/${id}/estado-cuenta`);

    if (error) {
        return (
            <div className="rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                {error}
            </div>
        );
    }

    if (!estadoCuenta) {
        return (
            <div className="flex justify-center py-12">
                <Spinner size="lg" />
            </div>
        );
    }

    const { credito, saldo, pagos, resumen_cuotas: resumenCuotas } = estadoCuenta;
    const cuotas = credito.cuotas ?? [];
    const cuotasPagadas = cuotas.filter((c) => c.estado === 'pagada').length;
    const totalPagado = pagos.reduce((total, pago) => total + parseFloat(pago.valor_pago), 0);

    return (
        <div className="flex min-h-full flex-col pb-6">
            <div className="mb-4 flex items-center justify-between print:hidden">
                <PageBreadcrumb pageTitle="Estado de cuenta" />
                <Button onClick={() => window.print()}>Imprimir</Button>
            </div>

            <div className="rounded-2xl border border-gray-200 bg-white p-6 md:p-8 dark:border-gray-800 dark:bg-white/3 print:border-0 print:p-0">
                <AccountStatementHeader company={company} credito={credito} cuotasPagadas={cuotasPagadas} />

                <AccountStatementPaymentsTable pagos={pagos} totalPagado={totalPagado} />

                <AccountStatementInstallmentsTable
                    cuotas={cuotas}
                    saldoCapitalTeoricoPorCuota={resumenCuotas.saldo_capital_teorico_por_cuota}
                    saldo={saldo}
                />

                <div className="mt-8 border-t border-gray-100 pt-4 text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
                    <p>
                        <strong>Observaciones:</strong> este documento es válido para pago total únicamente al día de generado.
                    </p>
                    <p>Cualquier inquietud, favor comunicarla por escrito a los canales de atención de la empresa.</p>
                </div>
            </div>
        </div>
    );
};
