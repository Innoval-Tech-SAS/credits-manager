import type { SelectOption } from '@ui/forms/types/select-option';

/** Módulos que hoy emiten eventos (o los emitirán al instrumentarse); el filtro acepta cualquier valor que exista en la bitácora. */
export const AUDIT_MODULE_LABELS: Record<string, string> = {
    auth: 'Autenticación',
    empresas: 'Empresas',
    usuarios: 'Usuarios',
    clientes: 'Clientes',
    empleadores: 'Empleadores',
    terceros: 'Terceros',
    proveedores: 'Proveedores',
    documentos: 'Documentos',
    creditos: 'Créditos',
    pagos: 'Pagos',
    cartera: 'Cartera',
    metas: 'Metas',
    contabilidad: 'Contabilidad',
    gastos: 'Gastos',
    geografia: 'Geografía',
    correo: 'Correo de cobranza',
    auditoria: 'Auditoría',
};

export const AUDIT_MODULE_OPTIONS: SelectOption[] = Object.entries(AUDIT_MODULE_LABELS).map(([value, label]) => ({ value, label }));
