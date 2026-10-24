import type { ApiActiveSession } from '../types/api-active-session';

export type SessionDetailModalProps = {
    session: ApiActiveSession | null;
    onClose: () => void;
};
