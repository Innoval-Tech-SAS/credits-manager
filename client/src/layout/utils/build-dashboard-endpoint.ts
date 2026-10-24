import type { DashboardFiltrosValor } from '../types/dashboard-filtros-valor';

/** `null` mientras el superadmin no elige empresa: sin empresa no hay nada que pedir. */
export function buildDashboardEndpoint(filtros: DashboardFiltrosValor, esSuperAdmin: boolean): string | null {
    if (esSuperAdmin && filtros.empresaId === null) {
        return null;
    }

    const parametros = new URLSearchParams({ periodo: filtros.periodo });

    if (filtros.empresaId !== null) {
        parametros.set('empresa_id', String(filtros.empresaId));
    }

    if (filtros.asesorId !== null) {
        parametros.set('asesor_id', String(filtros.asesorId));
    }

    if (filtros.cliente !== null) {
        parametros.set('cliente_id', String(filtros.cliente.id));
    }

    if (filtros.estado !== null) {
        parametros.set('estado', filtros.estado);
    }

    return `/dashboard?${parametros.toString()}`;
}
