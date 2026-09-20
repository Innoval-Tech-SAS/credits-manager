import type { SelectOption } from '@ui/forms/types/select-option';

/** Módulos que hoy emiten eventos (o los emitirán al instrumentarse); el filtro acepta cualquier valor que exista en la bitácora. */
export const AUDIT_MODULE_LABELS: Record<string, string> = {
    auth: 'Autenticación',
    empresas: 'Empresas',
    usuarios: 'Usuarios',
    clientes: 'Clientes',
    creditos: 'Créditos',
    pagos: 'Pagos',
    cartera: 'Cartera',
    contabilidad: 'Contabilidad',
    gastos: 'Gastos',
    auditoria: 'Auditoría',
};

export const AUDIT_MODULE_OPTIONS: SelectOption[] = Object.entries(AUDIT_MODULE_LABELS).map(([value, label]) => ({ value, label }));
