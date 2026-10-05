import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { ImportDecisionModals } from './import-decision-modals';
import { ImportStatusBadge } from './import-status-badge';
import { ImportSteps } from './import-steps';
import type { ApiImport } from '../types/api-import';

function importacion(overrides: Partial<ApiImport> = {}): ApiImport {
    return {
        id: 1,
        empresa_id: 1,
        usuario_id: 2,
        tipo: 'clientes',
        estado: 'pendiente_decision',
        archivo_original: 'clientes-prueba.csv',
        preferencia_crear_empleadores: false,
        preferencia_generar_asiento: false,
        cuenta_contrapartida_id: null,
        decision_crear_empleadores: null,
        decision_generar_asiento: null,
        comprobante_id: null,
        total_filas: 3,
        filas_aceptadas: 2,
        filas_rechazadas: 1,
        filas_pendientes: 0,
        empleadores_pendientes: 1,
        error: null,
        iniciada_en: null,
        validada_en: null,
        completada_en: null,
        creado_en: '2026-10-05 07:07:00',
        ...overrides,
    };
}

const sinDecisiones = { puede_contabilizar: false, requiere_decision_empleadores: false, requiere_decision_asiento: false };

describe('ImportSteps', () => {
    it('marca el paso activo con aria-current y los anteriores como hechos', () => {
        render(<ImportSteps steps={['Preparar', 'Subir', 'Validar']} currentStep={1} />);

        expect(screen.getByText('Subir').closest('li')?.getAttribute('aria-current')).toBe('step');
        expect(screen.getByText('Preparar').closest('li')?.getAttribute('aria-current')).toBeNull();
        expect(screen.getByText('Validar').closest('li')?.getAttribute('aria-current')).toBeNull();
    });
});

describe('ImportStatusBadge', () => {
    it('muestra la etiqueta del estado', () => {
        render(<ImportStatusBadge status="pendiente_decision" />);

        expect(screen.getByText('Espera tu decisión').textContent).toBe('Espera tu decisión');
    });
});

describe('ImportDecisionModals', () => {
    it('pregunta por los empleadores en singular y plural y responde con su valor', () => {
        const onDecide = vi.fn();
        const { unmount } = render(
            <ImportDecisionModals
                importacion={importacion({ empleadores_pendientes: 1 })}
                decisiones={{ ...sinDecisiones, requiere_decision_empleadores: true }}
                isSubmitting={false}
                onDecide={onDecide}
            />,
        );
        expect(screen.getByText('Hemos encontrado 1 empleador no creado. ¿Quieres crearlo?')).toBeTruthy();

        fireEvent.click(screen.getByRole('button', { name: 'Aceptar' }));
        expect(onDecide).toHaveBeenLastCalledWith('crear_empleadores', true);
        fireEvent.click(screen.getByRole('button', { name: 'Rechazar' }));
        expect(onDecide).toHaveBeenLastCalledWith('crear_empleadores', false);
        unmount();

        render(
            <ImportDecisionModals
                importacion={importacion({ empleadores_pendientes: 3 })}
                decisiones={{ ...sinDecisiones, requiere_decision_empleadores: true }}
                isSubmitting={false}
                onDecide={onDecide}
            />,
        );
        expect(screen.getByText('Hemos encontrado 3 empleadores no creados. ¿Quieres crearlos?')).toBeTruthy();
    });

    it('la pregunta del asiento aparece solo cuando los empleadores ya están resueltos', () => {
        const onDecide = vi.fn();
        const { rerender } = render(
            <ImportDecisionModals
                importacion={importacion({ tipo: 'creditos' })}
                decisiones={{ puede_contabilizar: true, requiere_decision_empleadores: true, requiere_decision_asiento: true }}
                isSubmitting={false}
                onDecide={onDecide}
            />,
        );
        expect(screen.queryByText('Datos validados. ¿Desea que se genere el asiento contable?')).toBeNull();

        rerender(
            <ImportDecisionModals
                importacion={importacion({ tipo: 'creditos' })}
                decisiones={{ puede_contabilizar: true, requiere_decision_empleadores: false, requiere_decision_asiento: true }}
                isSubmitting={false}
                onDecide={onDecide}
            />,
        );
        expect(screen.getByText('Datos validados. ¿Desea que se genere el asiento contable?')).toBeTruthy();
        fireEvent.click(screen.getByRole('button', { name: 'Rechazar' }));
        expect(onDecide).toHaveBeenLastCalledWith('generar_asiento', false);
    });

    it('no muestra nada cuando no hay decisiones abiertas', () => {
        const { container } = render(
            <ImportDecisionModals importacion={importacion()} decisiones={sinDecisiones} isSubmitting={false} onDecide={vi.fn()} />,
        );

        expect(container.innerHTML).toBe('');
    });

    it('deshabilita los botones mientras se envía una decisión', () => {
        render(
            <ImportDecisionModals
                importacion={importacion()}
                decisiones={{ ...sinDecisiones, requiere_decision_empleadores: true }}
                isSubmitting
                onDecide={vi.fn()}
            />,
        );

        expect((screen.getByRole('button', { name: 'Aceptar' }) as HTMLButtonElement).disabled).toBe(true);
        expect((screen.getByRole('button', { name: 'Rechazar' }) as HTMLButtonElement).disabled).toBe(true);
    });
});
