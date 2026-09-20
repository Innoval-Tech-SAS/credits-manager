import type { ApiOpeningBalanceImport } from '../types/api-opening-balance-import';

export const IMPORT_TYPE_LABELS: Record<ApiOpeningBalanceImport['tipo'], string> = {
    plan_cuentas: 'Plan de cuentas',
    terceros: 'Terceros',
    saldos_iniciales: 'Saldos iniciales',
};
