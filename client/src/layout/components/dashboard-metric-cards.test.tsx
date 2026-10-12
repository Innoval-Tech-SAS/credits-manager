import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { DashboardMetricCards } from './dashboard-metric-cards';
import type { ApiDashboardSummary } from '../types/api-dashboard-summary';

function metricas(overrides: Partial<ApiDashboardSummary['metricas']> = {}): ApiDashboardSummary['metricas'] {
    return {
        clientes_activos: 4,
        creditos_activos: 7,
        colocacion: null,
        recaudo: null,
        gastos: null,
        cartera: null,
        ...overrides,
    };
}

describe('DashboardMetricCards', () => {
    it('muestra los conteos que el backend envía', () => {
        render(<DashboardMetricCards metricas={metricas()} />);

        expect(screen.getByText('Clientes activos')).toBeTruthy();
        expect(screen.getByText('Créditos activos')).toBeTruthy();
    });

    it('sin permiso de clientes o créditos el backend envía null y la tarjeta no aparece', () => {
        render(<DashboardMetricCards metricas={metricas({ clientes_activos: null, creditos_activos: null })} />);

        expect(screen.queryByText('Clientes activos')).toBeNull();
        expect(screen.queryByText('Créditos activos')).toBeNull();
    });
});
