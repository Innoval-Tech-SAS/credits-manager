import type { LucideIcon } from 'lucide-react';

import type { StatCardAccent } from '../types/stat-card-accent';

export type StatCardProps = {
    label: string;
    value: string;
    icon: LucideIcon;
    accent?: StatCardAccent;
};
