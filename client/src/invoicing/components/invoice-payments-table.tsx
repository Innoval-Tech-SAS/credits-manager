import type { FC } from 'react';
import { useMemo } from 'react';

import { formatDateEs } from '../../shared/utils/format-date-es';
import { formatMoney } from '../../shared/utils/format-money';
import { Button } from '@ui/button/components/button';
import { DataTable } from '@ui/table/components/data-table';
import { PAYMENT_METHOD_LABELS } from '../constants/payment-methods';
import type { InvoicePaymentsTableProps } from '../props/invoice-payments-table-props';

/** Cobros y reversas en orden de registro. Un cobro ya reversado se marca y no admite otra reversa. */
export const InvoicePaymentsTable: FC<InvoicePaymentsTableProps> = ({ cobros, puedeReversar, onReversar }) => {
    const reversados = useMemo(
        () => new Set(cobros.filter((fila) => fila.tipo === 'reversa').map((fila) => fila.cobro_origen_id)),
        [cobros],
    );

    return (
        <DataTable
            columns={[
                { key: 'fecha', label: 'Fecha', render: (row) => formatDateEs(row.fecha) },
                {
                    key: 'movimiento',
                    label: 'Movimiento',
                    render: (row) =>
                        row.tipo === 'cobro' ? (
                            <span className="inline-block rounded-full bg-success-50 px-2.5 py-0.5 text-xs font-medium whitespace-nowrap text-success-600 dark:bg-success-500/15 dark:text-success-500">
                                Cobro
                            </span>
                        ) : (
                            <span className="inline-block rounded-full bg-warning-50 px-2.5 py-0.5 text-xs font-medium whitespace-nowrap text-warning-600 dark:bg-warning-500/15 dark:text-orange-400">
                                Reversa
                            </span>
                        ),
                },
                { key: 'medio', label: 'Medio', render: (row) => PAYMENT_METHOD_LABELS[row.medio] },
                { key: 'referencia', label: 'Referencia', render: (row) => row.referencia ?? '—' },
                { key: 'valor', label: 'Valor', render: (row) => formatMoney(row.valor) },
                { key: 'registrado_por', label: 'Registró', render: (row) => row.registrador?.nombre ?? '—' },
                {
                    key: 'estado',
                    label: 'Detalle',
                    render: (row) => {
                        if (row.tipo === 'reversa') {
                            return <span className="text-gray-600 dark:text-gray-400">Motivo: {row.motivo ?? '—'}</span>;
                        }

                        const reversado = reversados.has(row.id);

                        return (
                            <div className="flex flex-wrap items-center gap-3">
                                <span className={reversado ? 'text-error-600 dark:text-error-500' : 'text-success-600 dark:text-success-500'}>
                                    {reversado ? 'Reversado' : 'Vigente'}
                                </span>
                                {puedeReversar && !reversado && (
                                    <Button variant="outline" size="sm" onClick={() => onReversar(row)}>
                                        Reversar
                                    </Button>
                                )}
                            </div>
                        );
                    },
                },
            ]}
            rows={cobros}
            getRowKey={(row) => row.id}
            emptyMessage="Sin cobros registrados."
        />
    );
};
