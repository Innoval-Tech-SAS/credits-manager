import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { PermissionExportButton } from './permission-export-button';

const mocks = vi.hoisted(() => ({
    permisos: new Set<string>(),
}));

/** Usuario sin rol admin: cada permiso de la lista es una fila de `permissions`. */
vi.mock('../../auth/hooks/use-auth', () => ({
    useAuth: () => ({
        user: {
            id: 1,
            name: 'Exportador',
            username: 'exportador',
            role: 'exportador',
            isSuperAdmin: false,
            permissions: [...mocks.permisos].map((clave) => {
                const [modulo, accion] = clave.split('.');

                return { modulo, accion };
            }),
        },
    }),
}));

vi.mock('./export-button', () => ({
    ExportButton: ({ endpoint }: { endpoint: string }) => <button type="button">{`Exportar ${endpoint}`}</button>,
}));

function renderBoton(permiso: string) {
    render(<PermissionExportButton permiso={permiso} endpoint="/creditos/exportar" filters={{}} />);
}

describe('PermissionExportButton', () => {
    beforeEach(() => {
        mocks.permisos = new Set();
    });

    it('aparece con el permiso propio de esa exportación', () => {
        mocks.permisos = new Set(['creditos.exportar_listado']);
        renderBoton('creditos.exportar_listado');

        expect(screen.getByRole('button', { name: 'Exportar /creditos/exportar' })).toBeTruthy();
    });

    it('no aparece con el permiso de otra exportación del mismo módulo ni con la sola lectura', () => {
        mocks.permisos = new Set(['creditos.exportar_estado_cuenta', 'creditos.listar']);
        renderBoton('creditos.exportar_listado');

        expect(screen.queryByRole('button')).toBeNull();
    });
});
