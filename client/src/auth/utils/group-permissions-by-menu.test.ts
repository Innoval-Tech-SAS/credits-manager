import { describe, expect, it } from 'vitest';

import { groupPermissionsByMenu } from './group-permissions-by-menu';
import type { ApiPermisoPanelItem } from '../types/api-permiso';

function permiso(id: number, grupo: string, modulo: string, accion: string): ApiPermisoPanelItem {
    return { id, modulo, accion, grupo, grupo_etiqueta: `Grupo ${grupo}`, modulo_etiqueta: `Módulo ${modulo}`, accion_etiqueta: `Acción ${accion}` };
}

describe('groupPermissionsByMenu', () => {
    it('agrupa por grupo del menú y módulo conservando el orden recibido', () => {
        const grupos = groupPermissionsByMenu([
            permiso(1, 'cartera', 'clientes', 'listar'),
            permiso(2, 'cartera', 'clientes', 'crear'),
            permiso(3, 'cartera', 'creditos', 'listar'),
            permiso(4, 'administracion', 'usuarios', 'listar'),
        ]);

        expect(grupos.map((grupo) => grupo.grupo)).toEqual(['cartera', 'administracion']);
        expect(grupos[0].etiqueta).toBe('Grupo cartera');
        expect(grupos[0].modulos.map((modulo) => [modulo.modulo, modulo.permisos.map((item) => item.id)])).toEqual([
            ['clientes', [1, 2]],
            ['creditos', [3]],
        ]);
        expect(grupos[1].modulos[0].etiqueta).toBe('Módulo usuarios');
    });
});
