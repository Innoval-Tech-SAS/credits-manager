import type { CreditTransitionAction } from '../types/credit-transition-action';

export const CREDIT_TRANSITION_ACTION_CONFIG: Record<CreditTransitionAction, { titulo: string; verbo: string; variant: 'success' | 'danger' }> = {
    aprobar: { titulo: 'Aprobar crédito', verbo: 'aprobar', variant: 'success' },
    desembolsar: { titulo: 'Desembolsar crédito', verbo: 'desembolsar', variant: 'success' },
    cancelar: { titulo: 'Cancelar crédito', verbo: 'cancelar', variant: 'danger' },
};
