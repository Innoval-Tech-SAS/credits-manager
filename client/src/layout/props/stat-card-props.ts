import type { LucideIcon } from 'lucide-react';

import type { StatCardAccent } from '../types/stat-card-accent';
import type { StatCardCambio } from '../types/stat-card-cambio';

export type StatCardProps = {
    label: string;
    value: string;
    icon: LucideIcon;
    accent?: StatCardAccent;
    cambio?: StatCardCambio;
};
