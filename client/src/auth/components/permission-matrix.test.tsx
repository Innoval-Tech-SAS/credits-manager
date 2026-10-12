import { fireEvent, render, screen, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { PermissionMatrix } from './permission-matrix';
import type { ApiPermisoPanelItem } from '../types/api-permiso';

const PERMISOS: ApiPermisoPanelItem[] = [
    { id: 1, modulo: 'clientes', accion: 'listar', grupo: 'cartera', grupo_etiqueta: 'Cartera', modulo_etiqueta: 'Clientes', accion_etiqueta: 'Ver listado de clientes' },
    { id: 2, modulo: 'clientes', accion: 'crear', grupo: 'cartera', grupo_etiqueta: 'Cartera', modulo_etiqueta: 'Clientes', accion_etiqueta: 'Crear clientes' },
    { id: 3, modulo: 'clientes', accion: 'exportar_listado', grupo: 'cartera', grupo_etiqueta: 'Cartera', modulo_etiqueta: 'Clientes', accion_etiqueta: 'Exportar listado de clientes' },
    { id: 4, modulo: 'usuarios', accion: 'listar', grupo: 'administracion', grupo_etiqueta: 'Administración', modulo_etiqueta: 'Usuarios', accion_etiqueta: 'Ver usuarios' },
];

function renderMatrix(marcados: number[], options: { canChange?: (permiso: ApiPermisoPanelItem) => boolean; getOrigin?: (permiso: ApiPermisoPanelItem) => 'rol' | 'extra' | 'quitado' | null } = {}) {
    const onChange = vi.fn();
    render(
        <PermissionMatrix
            permisos={PERMISOS}
            isChecked={(permiso) => marcados.includes(permiso.id)}
            canChange={options.canChange ?? (() => true)}
            onChange={onChange}
            getOrigin={options.getOrigin}
        />,
    );

    return onChange;
}

function casillaDelModulo(nombre: string): HTMLInputElement {
    return screen.getByLabelText(`Todos los permisos de ${nombre}`) as HTMLInputElement;
}

describe('PermissionMatrix', () => {
    it('muestra grupos del menú, módulos y acciones con nombres legibles, no claves crudas', () => {
        renderMatrix([]);
        fireEvent.click(screen.getByRole('button', { name: 'Expandir todo' }));

        expect(screen.getByText('Cartera')).toBeTruthy();
        expect(screen.getByText('Administración')).toBeTruthy();
        expect(screen.getByText('Exportar listado de clientes')).toBeTruthy();
        expect(screen.queryByText('exportar_listado')).toBeNull();
        expect(screen.queryByText('clientes')).toBeNull();
    });

    it('cuenta los permisos marcados en total y por módulo', () => {
        renderMatrix([1, 2]);

        expect(screen.getByText('2 de 4 permisos marcados')).toBeTruthy();
        expect(screen.getByText('2/3')).toBeTruthy();
        expect(screen.getByText('0/1')).toBeTruthy();
    });

    it('la casilla del módulo queda en estado parcial con solo algunas acciones marcadas', () => {
        renderMatrix([1]);

        const clientes = casillaDelModulo('Clientes');
        expect(clientes.checked).toBe(false);
        expect(clientes.indeterminate).toBe(true);
        expect(clientes.getAttribute('aria-checked')).toBe('mixed');
        expect(casillaDelModulo('Usuarios').indeterminate).toBe(false);
    });

    it('marcar el módulo marca de una vez las acciones que faltan y desmarcarlo quita todas', () => {
        const onChange = renderMatrix([1]);

        fireEvent.click(casillaDelModulo('Clientes'));
        expect(onChange).toHaveBeenLastCalledWith([2, 3], true);
    });

    it('desmarcar un módulo completo quita todas sus acciones', () => {
        const onChange = renderMatrix([1, 2, 3]);

        const clientes = casillaDelModulo('Clientes');
        expect(clientes.checked).toBe(true);
        fireEvent.click(clientes);
        expect(onChange).toHaveBeenLastCalledWith([1, 2, 3], false);
    });

    it('marcar el módulo respeta las acciones que el usuario no puede conceder', () => {
        const onChange = renderMatrix([], { canChange: (permiso) => permiso.id !== 3 });

        fireEvent.click(casillaDelModulo('Clientes'));
        expect(onChange).toHaveBeenLastCalledWith([1, 2], true);

        fireEvent.click(screen.getByRole('button', { name: /Clientes/ }));
        expect((screen.getByLabelText('Exportar listado de clientes') as HTMLInputElement).disabled).toBe(true);
        expect((screen.getByLabelText('Crear clientes') as HTMLInputElement).disabled).toBe(false);
    });

    it('sin nada que pueda cambiar, la casilla del módulo queda deshabilitada', () => {
        renderMatrix([], { canChange: (permiso) => permiso.modulo !== 'usuarios' });

        expect(casillaDelModulo('Usuarios').disabled).toBe(true);
    });

    it('en el usuario distingue lo que viene del rol, las excepciones concedidas y las quitadas', () => {
        const origenes: Record<number, 'rol' | 'extra' | 'quitado'> = { 1: 'rol', 2: 'extra', 3: 'quitado' };
        renderMatrix([1, 2], { getOrigin: (permiso) => origenes[permiso.id] ?? null });
        fireEvent.click(screen.getByRole('button', { name: /Clientes/ }));

        const fila = (texto: string) => screen.getByText(texto).closest('li') as HTMLElement;
        expect(within(fila('Ver listado de clientes')).getByText('Rol')).toBeTruthy();
        expect(within(fila('Crear clientes')).getByText('Extra')).toBeTruthy();
        expect(within(fila('Exportar listado de clientes')).getByText('Quitado')).toBeTruthy();
    });

    it('la búsqueda ignora tildes y muestra solo las acciones que coinciden', () => {
        renderMatrix([]);

        fireEvent.change(screen.getByPlaceholderText('Buscar permiso…'), { target: { value: 'exportar' } });
        expect(screen.getByText('Exportar listado de clientes')).toBeTruthy();
        expect(screen.queryByText('Crear clientes')).toBeNull();
        expect(screen.queryByText('Administración')).toBeNull();

        fireEvent.change(screen.getByPlaceholderText('Buscar permiso…'), { target: { value: 'administracion' } });
        expect(screen.getByText('Ver usuarios')).toBeTruthy();

        fireEvent.change(screen.getByPlaceholderText('Buscar permiso…'), { target: { value: 'zzz' } });
        expect(screen.getByText('Ningún permiso coincide con la búsqueda.')).toBeTruthy();
    });
});
