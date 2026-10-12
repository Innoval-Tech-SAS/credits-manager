import { describe, expect, it } from 'vitest';

import { NAV_ITEMS } from './nav-items';

describe('NAV_ITEMS', () => {
    const subItems = NAV_ITEMS.flatMap((item) => item.subItems ?? []);
    const conPermiso = subItems.filter((subItem) => subItem.moduleKey);

    it('cada sub-item protegido por permiso exige uno propio, no el de otra pantalla', () => {
        const claves = conPermiso.map((subItem) => `${subItem.moduleKey}.${subItem.action ?? 'listar'}`);

        expect(new Set(claves).size).toBe(claves.length);
    });

    it('todo sub-item tiene una regla de visibilidad: permiso, solo admin o regla propia', () => {
        const sinRegla = subItems.filter((subItem) => !subItem.moduleKey && !subItem.adminOnly && !subItem.isVisible);

        expect(sinRegla.map((subItem) => subItem.name)).toEqual([]);
    });

    it('las notas crédito y débito no dependen del permiso de facturas', () => {
        const claveDe = (nombre: string) => {
            const subItem = conPermiso.find((item) => item.name === nombre);

            return `${subItem?.moduleKey}.${subItem?.action}`;
        };

        expect(claveDe('Facturas')).toBe('facturacion.listar_facturas');
        expect(claveDe('Notas crédito')).toBe('facturacion.listar_notas_credito');
        expect(claveDe('Notas débito')).toBe('facturacion.listar_notas_debito');
    });
});
