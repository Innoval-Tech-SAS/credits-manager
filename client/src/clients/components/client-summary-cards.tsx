import type { FC } from 'react';

import { formatMoney } from '../../shared/utils/format-money';
import { CLIENT_STATUS_BADGE_CLASSES } from '../constants/client-status-badge-classes';
import type { ClientSummaryCardsProps } from '../props/client-summary-cards-props';

export const ClientSummaryCards: FC<ClientSummaryCardsProps> = ({ cliente, saldoConsolidado, creditosCount }) => (
    <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3">
            <p className="text-sm text-gray-500 dark:text-gray-400">Documento</p>
            <p className="mt-1 text-title-sm font-semibold text-gray-800 dark:text-white/90">
                {cliente.tercero.tipo_documento.toUpperCase()} {cliente.tercero.numero_documento}
            </p>
        </div>
        <div className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3">
            <p className="text-sm text-gray-500 dark:text-gray-400">Saldo consolidado</p>
            <p className="mt-1 text-title-sm font-semibold text-brand-600 dark:text-brand-400">{formatMoney(saldoConsolidado)}</p>
        </div>
        <div className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3">
            <p className="text-sm text-gray-500 dark:text-gray-400">Créditos</p>
            <p className="mt-1 text-title-sm font-semibold text-gray-800 dark:text-white/90">{creditosCount}</p>
        </div>
        <div className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3">
            <p className="text-sm text-gray-500 dark:text-gray-400">Estado</p>
            <span
                className={`mt-1 inline-block rounded-full px-2.5 py-0.5 text-xs font-medium ${CLIENT_STATUS_BADGE_CLASSES[cliente.activo ? 'activo' : 'inactivo']}`}
            >
                {cliente.activo ? 'Activo' : 'Inactivo'}
            </span>
        </div>
    </div>
);
