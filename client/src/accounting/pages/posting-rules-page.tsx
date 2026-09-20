import type { FC } from 'react';
import { useState } from 'react';

import { useResource } from '../../shared/hooks/use-resource';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { Modal } from '@ui/modal/components/modal';
import { DataTable } from '@ui/table/components/data-table';
import { PostingRuleForm } from '../components/posting-rule-form';
import { POSTING_EVENT_LABELS } from '../constants/posting-event-options';
import type { ApiAccount } from '../types/api-account';
import type { ApiPostingRulesResponse } from '../types/api-posting-rule';
import type { ApiVoucherType } from '../types/api-voucher-type';
import type { PaginatedResponse } from '../../shared/types/paginated-response';

export const PostingRulesPage: FC = () => {
    const [refreshToken, setRefreshToken] = useState(0);
    const [eventoAConfigurar, setEventoAConfigurar] = useState<string | null>(null);

    const { data: cuentasData } = useResource<{ cuentas: ApiAccount[] }>('/contabilidad/plan-cuentas');
    const { data: tiposData } = useResource<PaginatedResponse<ApiVoucherType>>('/contabilidad/tipos-comprobante?per_page=100');
    const { data: reglasData, isLoading } = useResource<ApiPostingRulesResponse>(
        `/contabilidad/reglas${refreshToken ? `?_r=${refreshToken}` : ''}`,
    );

    const cuentas = (cuentasData?.cuentas ?? []).filter((cuenta) => cuenta.permite_movimiento);
    const tipos = tiposData?.data ?? [];
    const eventos = reglasData?.eventos ?? [];
    const reglas = reglasData?.reglas ?? {};

    const nombreCuenta = (id?: number) => {
        const cuenta = cuentas.find((item) => item.id === id);
        return cuenta ? `${cuenta.codigo} - ${cuenta.nombre}` : '—';
    };
    const nombreTipo = (id?: number) => {
        const tipo = tipos.find((item) => item.id === id);
        return tipo ? `${tipo.codigo} - ${tipo.nombre}` : '—';
    };

    return (
        <div className="flex min-h-full flex-col gap-6 pb-6">
            <PageBreadcrumb pageTitle="Reglas de contabilización" />

            <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                <p className="text-sm text-gray-500 dark:text-gray-400">
                    Defina qué cuentas se afectan automáticamente para cada evento de negocio. Mientras un evento no esté
                    configurado, la operación se registra igualmente pero sin generar comprobante contable automático.
                </p>
            </div>

            <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                <DataTable
                    columns={[
                        { key: 'evento', label: 'Evento', render: (evento) => POSTING_EVENT_LABELS[evento] ?? evento },
                        { key: 'debito', label: 'Cuenta débito', render: (evento) => nombreCuenta(reglas[evento]?.cuenta_debito_id) },
                        { key: 'credito', label: 'Cuenta crédito', render: (evento) => nombreCuenta(reglas[evento]?.cuenta_credito_id) },
                        { key: 'tipo', label: 'Tipo de comprobante', render: (evento) => nombreTipo(reglas[evento]?.tipo_comprobante_id) },
                        {
                            key: 'estado',
                            label: 'Estado',
                            render: (evento) => (
                                <span
                                    className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                        reglas[evento]
                                            ? 'bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-400'
                                            : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'
                                    }`}
                                >
                                    {reglas[evento] ? 'Configurado' : 'Sin configurar'}
                                </span>
                            ),
                        },
                        {
                            key: 'accion',
                            label: '',
                            render: (evento) => (
                                <button
                                    type="button"
                                    onClick={() => setEventoAConfigurar(evento)}
                                    className="text-sm font-medium text-brand-500 hover:text-brand-600"
                                >
                                    {reglas[evento] ? 'Editar' : 'Configurar'}
                                </button>
                            ),
                        },
                    ]}
                    rows={eventos}
                    getRowKey={(evento) => evento}
                    loading={isLoading}
                    emptyMessage="No hay eventos configurables."
                />
            </div>

            <Modal isOpen={eventoAConfigurar !== null} onClose={() => setEventoAConfigurar(null)} size="lg">
                {eventoAConfigurar && (
                    <>
                        <h4 className="mb-4 text-lg font-semibold text-gray-800 dark:text-white/90">
                            {POSTING_EVENT_LABELS[eventoAConfigurar] ?? eventoAConfigurar}
                        </h4>
                        <PostingRuleForm
                            evento={eventoAConfigurar}
                            reglaActual={reglas[eventoAConfigurar]}
                            cuentas={cuentas}
                            tipos={tipos}
                            onSaved={() => {
                                setRefreshToken((token) => token + 1);
                                setEventoAConfigurar(null);
                            }}
                        />
                    </>
                )}
            </Modal>
        </div>
    );
};
