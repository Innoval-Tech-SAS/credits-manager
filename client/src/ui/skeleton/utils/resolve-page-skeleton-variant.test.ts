import { describe, expect, it } from 'vitest';

import { resolvePageSkeletonVariant } from './resolve-page-skeleton-variant';

describe('resolvePageSkeletonVariant', () => {
    it.each([
        ['/', 'dashboard'],
        ['/calendario', 'calendar'],
        ['/cartera', 'summary'],
        ['/metas', 'summary'],
        ['/clientes/12', 'summary'],
        ['/creditos/7', 'summary'],
        ['/pagos/creditos/7', 'summary'],
        ['/contabilidad/libro-diario', 'report'],
        ['/contabilidad/cuentas-por-pagar', 'report'],
        ['/cartera/reporte-empleador', 'report'],
        ['/pagos/buscar', 'report'],
        ['/facturacion/consecutivos', 'report'],
        ['/empresa', 'form'],
        ['/correo', 'form'],
        ['/metas/configuracion', 'form'],
        ['/clientes/crear', 'form'],
        ['/creditos/crear', 'form'],
        ['/clientes/12/editar', 'form'],
        ['/pagos/creditos/7/liquidar', 'form'],
        ['/contabilidad/comprobantes/causar-concepto', 'form'],
        ['/contabilidad/comprobantes/31', 'detail'],
        ['/creditos/7/estado-cuenta', 'detail'],
        ['/conciliacion/extractos/3/conciliar', 'detail'],
        ['/clientes', 'list'],
        ['/empresas', 'list'],
        ['/geografia/municipios', 'list'],
        ['/respaldos', 'list'],
    ] as const)('%s → %s', (pathname, esperado) => {
        expect(resolvePageSkeletonVariant(pathname)).toBe(esperado);
    });

    it('ignora la barra final', () => {
        expect(resolvePageSkeletonVariant('/clientes/crear/')).toBe('form');
    });

    it('el inicio de superadmin tiene su propia silueta, no la del dashboard completo', () => {
        expect(resolvePageSkeletonVariant('/', true)).toBe('superadmin-home');
    });

    it('esSuperAdmin no cambia la silueta de otras rutas', () => {
        expect(resolvePageSkeletonVariant('/clientes', true)).toBe('list');
    });
});
