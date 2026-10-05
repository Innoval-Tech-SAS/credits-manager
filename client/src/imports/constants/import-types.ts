export const IMPORT_TYPES = ['clientes', 'creditos', 'plan_cuotas', 'pagos', 'saldos_iniciales', 'plan_cuentas', 'terceros'] as const;

export type ImportType = (typeof IMPORT_TYPES)[number];

export const IMPORT_TYPE_LABELS: Record<ImportType, string> = {
    clientes: 'Clientes',
    creditos: 'Créditos',
    plan_cuotas: 'Plan de cuotas',
    pagos: 'Pagos',
    saldos_iniciales: 'Saldos contables iniciales',
    plan_cuentas: 'Plan de cuentas (formato anterior)',
    terceros: 'Terceros (formato anterior)',
};

/** Orden en que deben cargarse: cada hoja depende de las anteriores. */
export const IMPORT_TYPE_OPTIONS = [
    { value: 'clientes', label: '1. Clientes' },
    { value: 'creditos', label: '2. Créditos' },
    { value: 'plan_cuotas', label: '3. Plan de cuotas' },
    { value: 'pagos', label: '4. Pagos' },
    { value: 'saldos_iniciales', label: '5. Saldos contables iniciales' },
    { value: 'plan_cuentas', label: 'Plan de cuentas (formato anterior)' },
    { value: 'terceros', label: 'Terceros (formato anterior)' },
] satisfies { value: ImportType; label: string }[];

export const IMPORT_TYPES_WITH_EMPLOYER_CHECK: ImportType[] = ['clientes'];

export const IMPORT_TYPES_WITH_ACCOUNTING: ImportType[] = ['creditos', 'pagos'];

/** Solo el asiento de apertura de cartera necesita una cuenta de contrapartida elegida por el usuario. */
export const IMPORT_TYPES_WITH_OFFSET_ACCOUNT: ImportType[] = ['creditos'];
