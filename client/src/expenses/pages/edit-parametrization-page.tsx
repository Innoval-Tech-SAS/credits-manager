import type { FC } from 'react';
import { useNavigate, useParams } from 'react-router';

import { useResource } from '../../shared/hooks/use-resource';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { FormSkeletonBody } from '@ui/skeleton/components/form-skeleton-body';
import { ParametrizationForm } from '../components/parametrization-form';
import type { ApiAccount } from '../../accounting/types/api-account';
import type { ApiVoucherType } from '../../accounting/types/api-voucher-type';
import type { PaginatedResponse } from '../../shared/types/paginated-response';
import type { ApiExpenseType } from '../types/api-expense-type';
import type { ApiParametrization } from '../types/api-parametrization';

export const EditParametrizationPage: FC = () => {
    const navigate = useNavigate();
    const { id } = useParams<{ id: string }>();

    const { data: parametrizacionData, error } = useResource<{ parametrizacion: ApiParametrization }>(`/gastos/parametrizaciones/${id}`);
    const { data: tiposGastoData, isLoading: isLoadingTiposGasto } = useResource<{ tipos_gasto: ApiExpenseType[] }>('/gastos/tipos-gasto');
    const { data: cuentasData, isLoading: isLoadingCuentas } = useResource<{ cuentas: ApiAccount[] }>('/contabilidad/plan-cuentas');
    const { data: tiposComprobanteData, isLoading: isLoadingTiposComprobante } = useResource<PaginatedResponse<ApiVoucherType>>(
        '/contabilidad/tipos-comprobante?per_page=100',
    );

    const parametrizacion = parametrizacionData?.parametrizacion ?? null;
    const tiposGasto = tiposGastoData?.tipos_gasto ?? [];
    const cuentas = (cuentasData?.cuentas ?? []).filter((cuenta) => cuenta.permite_movimiento && cuenta.activa);
    const tiposComprobante = tiposComprobanteData?.data ?? [];
    const isLoading = !parametrizacion || isLoadingTiposGasto || isLoadingCuentas || isLoadingTiposComprobante;

    const handleSaved = (guardada: ApiParametrization) => {
        navigate(`/gastos/parametrizaciones?tipo_gasto_id=${guardada.tipo_gasto_id}`, {
            replace: true,
            state: { message: 'Parametrización actualizada correctamente.' },
        });
    };

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Editar parametrización de tipo de gasto" />

            <div className="flex-1 rounded-2xl border border-gray-200 bg-white p-6 md:p-8 dark:border-gray-800 dark:bg-white/3">
                {error && (
                    <div className="mb-6 rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                        {error}
                    </div>
                )}

                {!error && isLoading && <FormSkeletonBody />}

                {!isLoading && parametrizacion && (
                    <>
                        <p className="mb-6 rounded-lg border border-warning-500 bg-warning-50 px-4 py-3 text-sm text-warning-600 dark:border-warning-800 dark:bg-warning-500/10 dark:text-orange-400">
                            Los cambios aplican a los gastos que se causen desde ahora; los ya causados conservan su asiento. Para cambiar desde
                            cuándo rige una configuración, crea una nueva versión de vigencia.
                        </p>

                        <ParametrizationForm
                            tiposGasto={tiposGasto}
                            cuentas={cuentas}
                            tiposComprobante={tiposComprobante}
                            parametrizacionId={parametrizacion.id}
                            initialData={parametrizacion}
                            onSaved={handleSaved}
                        />
                    </>
                )}
            </div>
        </div>
    );
};
