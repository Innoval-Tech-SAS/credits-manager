import type { FC } from 'react';

import { INVOICE_SITUATION_BADGE_CLASSES, INVOICE_SITUATION_LABELS } from '../constants/invoice-situation';
import type { InvoiceSituationBadgeProps } from '../props/invoice-situation-badge-props';

export const InvoiceSituationBadge: FC<InvoiceSituationBadgeProps> = ({ situacion }) => (
    <span className={`inline-block rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap ${INVOICE_SITUATION_BADGE_CLASSES[situacion]}`}>
        {INVOICE_SITUATION_LABELS[situacion]}
    </span>
);
