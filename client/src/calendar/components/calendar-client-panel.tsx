import type { FC, ReactNode } from 'react';

import { WhatsAppButton } from '../../clients/components/whatsapp-button';
import { useResource } from '../../shared/hooks/use-resource';
import { formatDateEs } from '../../shared/utils/format-date-es';
import { formatMoney } from '../../shared/utils/format-money';
import type { CalendarClientPanelProps } from '../props/calendar-client-panel-props';
import type { ApiCalendarClientSummary } from '../types/api-calendar-client-summary';

function iniciales(nombre: string): string {
    return nombre
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((parte) => parte[0].toUpperCase())
        .join('');
}

const Fila: FC<{ etiqueta: string; children: ReactNode }> = ({ etiqueta, children }) => (
    <div className="flex items-center justify-between gap-4 py-3.5">
        <dt className="shrink-0 text-gray-500 dark:text-gray-400">{etiqueta}</dt>
        <dd className="min-w-0 text-right font-semibold text-gray-800 dark:text-white/90">{children}</dd>
    </div>
);

export const CalendarClientPanel: FC<CalendarClientPanelProps> = ({ detalle }) => {
    const { data: resumen, error } = useResource<ApiCalendarClientSummary>(`/clientes/${detalle.clienteId}/resumen`);

    const ultimoPago = resumen?.ultimos_pagos[0] ?? null;
    const enMora = resumen !== null && resumen !== undefined && resumen.cartera.dias_mora > 0;

    return (
        <aside className="flex flex-col gap-6 bg-gray-50 p-6 sm:p-8 md:border-r md:border-gray-200 dark:bg-white/5 dark:md:border-gray-800">
            <div className="flex items-center gap-4">
                <div className="flex size-14 shrink-0 items-center justify-center rounded-full bg-brand-500 text-lg font-semibold text-white">
                    {iniciales(detalle.clienteNombre)}
                </div>
                <div className="min-w-0">
                    <p className="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">Cliente</p>
                    <h3 className="truncate text-lg font-semibold text-gray-900 dark:text-white/90">{detalle.clienteNombre}</h3>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        {detalle.tipoDocumento.toUpperCase()} {detalle.numeroDocumento}
                    </p>
                </div>
            </div>

            <dl className="divide-y divide-gray-200 text-sm dark:divide-gray-800">
                <Fila etiqueta="Celular">
                    <span className="inline-flex items-center gap-2">
                        {detalle.celular ?? 'Sin celular'}
                        <WhatsAppButton variant="icono" nombre={detalle.clienteNombre} celular={detalle.celular} />
                    </span>
                </Fila>
                <Fila etiqueta="Crédito que paga">#{detalle.creditoId}</Fila>
                <Fila etiqueta="Créditos con la entidad">{detalle.creditosConEntidad}</Fila>
                <Fila etiqueta="Estado de cartera">
                    {error && <span className="text-error-600 dark:text-error-400">No disponible</span>}
                    {!error && resumen === null && <span className="text-gray-400">Cargando…</span>}
                    {resumen && (
                        <span className={enMora ? 'text-error-600 dark:text-error-400' : 'text-success-600 dark:text-success-400'}>
                            {enMora ? `En mora · ${resumen.cartera.dias_mora} días` : 'Al día'}
                        </span>
                    )}
                </Fila>
                <Fila etiqueta="Saldo con la entidad">{resumen ? formatMoney(resumen.saldo_consolidado) : '—'}</Fila>
                <Fila etiqueta="Último pago">
                    {resumen && (ultimoPago ? `${formatDateEs(ultimoPago.fecha_pago)} · ${formatMoney(ultimoPago.valor_pago)}` : 'Sin pagos')}
                    {!resumen && '—'}
                </Fila>
            </dl>
        </aside>
    );
};
