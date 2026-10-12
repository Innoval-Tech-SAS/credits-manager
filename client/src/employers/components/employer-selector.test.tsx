import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { EmployerSelector } from './employer-selector';

const mocks = vi.hoisted(() => ({
    permisos: new Set<string>(),
}));

vi.mock('../../auth/hooks/use-permission', () => ({
    usePermission: (modulo: string, accion: string) => mocks.permisos.has(`${modulo}.${accion}`),
}));

vi.mock('../../shared/hooks/use-typeahead', () => ({
    useTypeahead: () => ({
        query: '',
        setQuery: vi.fn(),
        isOpen: true,
        setIsOpen: vi.fn(),
        results: [],
        isLoading: false,
        reset: vi.fn(),
    }),
}));

function renderSelector(): void {
    render(
        <MemoryRouter>
            <EmployerSelector id="empleador" selected={null} onSelect={vi.fn()} />
        </MemoryRouter>,
    );
}

describe('EmployerSelector', () => {
    beforeEach(() => {
        mocks.permisos = new Set();
    });

    it('con empleadores.listar pero sin empleadores.crear busca y no ofrece crear', () => {
        mocks.permisos = new Set(['empleadores.listar']);
        renderSelector();

        expect((screen.getByPlaceholderText('Busca un empleador…') as HTMLInputElement).disabled).toBe(false);
        expect(screen.queryByText('+ Crear empleador')).toBeNull();
    });

    it('con empleadores.crear ofrece el atajo de crear', () => {
        mocks.permisos = new Set(['empleadores.listar', 'empleadores.crear']);
        renderSelector();

        expect(screen.getByText('+ Crear empleador')).toBeTruthy();
    });

    it('los permisos de clientes no habilitan la búsqueda de empleadores', () => {
        mocks.permisos = new Set(['clientes.crear', 'clientes.editar']);
        renderSelector();

        expect((screen.getByPlaceholderText('Sin permiso para consultar empleadores') as HTMLInputElement).disabled).toBe(true);
    });
});
