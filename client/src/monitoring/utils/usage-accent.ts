import type { StatCardAccent } from '../../layout/types/stat-card-accent';

/** Por encima del 85 % un recurso se marca en rojo; sin lectura, neutro. */
export function usageAccent(percentage: number | null | undefined): StatCardAccent {
    if (percentage === null || percentage === undefined) {
        return 'neutral';
    }

    return percentage >= 85 ? 'error' : 'success';
}
