import type { FC } from 'react';
import { useNavigate } from 'react-router';
import { HandCoins } from 'lucide-react';

import { Button } from '@ui/button/components/button';
import { DataTable } from '@ui/table/components/data-table';
import { formatMoney } from '../../shared/utils/format-money';
import type { PagosDelDiaCardProps } from '../props/pagos-del-dia-card-props';

export const PagosDelDiaCard: FC<PagosDelDiaCardProps> = ({ pagos, puedeRegistrarPago }) => {
    const navigate = useNavigate();

    return (
        <section className="h-full rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3 sm:p-6">
            <h2 className="mb-4 text-lg font-semibold text-gray-800 dark:text-white/90">Pagos del día</h2>

            <DataTable
                columns={[
                    { key: 'cliente_nombre', label: 'Cliente' },
                    { key: 'numero_cuota', label: 'Cuota #' },
                    { key: 'valor', label: 'Valor', render: (row) => formatMoney(row.valor) },
                    { key: 'pendiente', label: 'Pendiente', render: (row) => formatMoney(row.pendiente) },
                    {
                        key: 'acciones',
                        label: '',
                        render: (row) =>
                            puedeRegistrarPago ? (
                                <div className="text-right">
                                    <Button collapseBelow="md" startIcon={<HandCoins className="size-4" />} size="sm" variant="outline" onClick={() => navigate(`/creditos/${row.credito_id}/pagos/nuevo`)}>
                                        Registrar pago
                                    </Button>
                                </div>
                            ) : null,
                    },
                ]}
                rows={pagos}
                getRowKey={(row) => row.cuota_id}
                emptyMessage="No hay cuotas con vencimiento hoy."
            />
        </section>
    );
};
