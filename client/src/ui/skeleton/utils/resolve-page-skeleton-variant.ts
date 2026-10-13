import type { PageSkeletonVariant } from '../types/page-skeleton-variant';

/**
 * Rutas cuya forma no se deduce de su último segmento. Se revisan en orden, antes de las reglas genéricas.
 * Al agregar una pantalla de informe, ficha con resumen o ajustes, regístrala aquí para que su esqueleto se le parezca.
 */
const RUTAS: readonly (readonly [RegExp, PageSkeletonVariant])[] = [
    [/^\/$/, 'dashboard'],
    [/^\/calendario$/, 'calendar'],

    // Fichas con tarjetas de métricas arriba.
    [/^\/(cartera|metas)$/, 'summary'],
    [/^\/(clientes|creditos)\/(?!crear$)[^/]+$/, 'summary'],
    [/^\/pagos\/creditos\/[^/]+$/, 'summary'],

    // Informes y ajustes: una tarjeta de filtros (o de parámetros) y debajo la de resultados.
    [
        /^\/contabilidad\/(libro-diario|libro-mayor|caja-bancos|balance-prueba|estados-financieros|auxiliares|conciliacion-apertura|cuentas-por-pagar|cuentas-por-cobrar|reglas)$/,
        'report',
    ],
    [/^\/(gastos\/por-centro-costo|cartera\/reporte-empleador|pagos\/buscar)$/, 'report'],
    [/^\/facturacion\/(consecutivos|parametrizacion-conceptos)$/, 'report'],

    // Ajustes que son un formulario aunque su ruta no lo diga.
    [/^\/(empresa|correo)$/, 'form'],
];

/** Último segmento de las rutas que abren un formulario (`/clientes/crear`, `/pagos/creditos/:id/liquidar`…). */
const SEGMENTOS_FORMULARIO = new Set(['crear', 'editar', 'nuevo', 'nueva', 'liquidar', 'causar-concepto', 'cruzar', 'configuracion']);

/** Último segmento de las rutas de detalle que no terminan en un id (`/creditos/:id/estado-cuenta`…). */
const SEGMENTOS_DETALLE = new Set(['estado-cuenta', 'conciliar']);

const ES_ID = /^(\d+|[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})$/i;

/**
 * Elige la silueta por la forma de la ruta, sin conocer la página: así el esqueleto se parece a lo que va a llegar.
 * `esSuperAdmin` solo importa en `/`: el inicio de superadmin es filtros + "elige una empresa", no el dashboard completo.
 */
export function resolvePageSkeletonVariant(pathname: string, esSuperAdmin = false): PageSkeletonVariant {
    const ruta = pathname.length > 1 ? pathname.replace(/\/+$/, '') : pathname;

    if (ruta === '/' && esSuperAdmin) {
        return 'superadmin-home';
    }

    const regla = RUTAS.find(([patron]) => patron.test(ruta));

    if (regla) {
        return regla[1];
    }

    const ultimo = ruta.split('/').filter(Boolean).at(-1) ?? '';

    if (SEGMENTOS_FORMULARIO.has(ultimo)) {
        return 'form';
    }

    if (SEGMENTOS_DETALLE.has(ultimo) || ES_ID.test(ultimo)) {
        return 'detail';
    }

    return 'list';
}
