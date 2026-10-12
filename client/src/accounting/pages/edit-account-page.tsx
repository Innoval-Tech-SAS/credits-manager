import type { FC } from 'react';
import { useNavigate, useParams } from 'react-router';

import { useResource } from '../../shared/hooks/use-resource';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { Spinner } from '@ui/spinner/components/spinner';
import { AccountForm } from '../components/account-form';
import type { ApiAccount } from '../types/api-account';

/** Sin endpoint de detalle: la cuenta sale del mismo plan de cuentas que alimenta el selector de cuenta padre. */
export const EditAccountPage: FC = () => {
    const navigate = useNavigate();
    const { id } = useParams<{ id: string }>();

    const { data, error, isLoading } = useResource<{ cuentas: ApiAccount[] }>('/contabilidad/plan-cuentas');
    const cuentas = data?.cuentas ?? [];
    const cuenta = cuentas.find((item) => String(item.id) === id) ?? null;

    const handleSaved = () => {
        navigate('/contabilidad/plan-cuentas', { replace: true, state: { message: 'Cuenta actualizada correctamente.' } });
    };

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Editar cuenta" />

            <div className="flex-1 rounded-2xl border border-gray-200 bg-white p-6 md:p-8 dark:border-gray-800 dark:bg-white/3">
                {error && (
                    <div className="mb-6 rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                        {error}
                    </div>
                )}

                {isLoading && (
                    <div className="flex justify-center py-12">
                        <Spinner size="lg" />
                    </div>
                )}

                {data && !cuenta && <p className="text-sm text-gray-500 dark:text-gray-400">La cuenta no existe.</p>}

                {cuenta && <AccountForm cuentas={cuentas} accountId={cuenta.id} initialData={cuenta} onSaved={handleSaved} />}
            </div>
        </div>
    );
};
