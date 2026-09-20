/** Filtros de la bitácora tal como viajan en el query string (todo llega como texto; `''` = sin filtro). */
export type AuditFilters = {
    desde: string;
    hasta: string;
    modulo: string;
    accion: string;
    autor: string;
    entidad: string;
    empresa_id: string;
};
