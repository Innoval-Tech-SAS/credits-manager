import type { LiveStatus } from '../types/live-status';

export type LiveIndicatorProps = {
    status: LiveStatus;
    generatedAt: string | null;
};
