export const IMPORT_TYPES = [
    'clientes',
    'creditos',
    'plan_cuotas',
    'pagos',
    'saldos_iniciales',
    'plan_cuentas',
    'terceros',
    'gastos',
    'actividades_economicas',
    'valores_uvt',
    'tarifas_reteica',
] as const;

export type ImportType = (typeof IMPORT_TYPES)[number];

export const IMPORT_TYPE_LABELS: Record<ImportType, string> = {
    clientes: 'Clientes',
    creditos: 'Créditos',
    plan_cuotas: 'Plan de cuotas',
    pagos: 'Pagos',
    saldos_iniciales: 'Saldos contables iniciales',
    plan_cuentas: 'Plan de cuentas (formato anterior)',
    terceros: 'Terceros (formato anterior)',
    gastos: 'Gastos',
    actividades_economicas: 'Actividades económicas (CIIU)',
    valores_uvt: 'Valores UVT',
    tarifas_reteica: 'Tarifas ReteICA',
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
    { value: 'gastos', label: 'Gastos y costos' },
    { value: 'actividades_economicas', label: 'Actividades económicas (CIIU)' },
    { value: 'valores_uvt', label: 'Valores UVT' },
    { value: 'tarifas_reteica', label: 'Tarifas ReteICA' },
] satisfies { value: ImportType; label: string }[];

/** Permiso "modulo.accion" que exige cada hoja (espejo de `DefinicionImportacion` en el backend). */
export const IMPORT_TYPE_PERMISSIONS: Record<ImportType, string> = {
    clientes: 'importaciones.importar_clientes',
    creditos: 'importaciones.importar_creditos',
    plan_cuotas: 'importaciones.importar_plan_cuotas',
    pagos: 'importaciones.importar_pagos',
    saldos_iniciales: 'importaciones.importar_saldos_iniciales',
    plan_cuentas: 'importaciones.importar_plan_cuentas',
    terceros: 'importaciones.importar_terceros',
    gastos: 'importaciones.importar_gastos',
    actividades_economicas: 'importaciones.importar_actividades_economicas',
    valores_uvt: 'importaciones.importar_valores_uvt',
    tarifas_reteica: 'importaciones.importar_tarifas_reteica',
};

/** Con cualquiera de ellos se entra al módulo; el listado y cada carga se filtran por el de su hoja. */
export const IMPORT_PERMISSIONS: string[] = Object.values(IMPORT_TYPE_PERMISSIONS);

export const IMPORT_TYPES_WITH_EMPLOYER_CHECK: ImportType[] = ['clientes'];

export const IMPORT_TYPES_WITH_ACCOUNTING: ImportType[] = ['creditos', 'pagos'];

/** Solo el asiento de apertura de cartera necesita una cuenta de contrapartida elegida por el usuario. */
export const IMPORT_TYPES_WITH_OFFSET_ACCOUNT: ImportType[] = ['creditos'];
