import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { InvoiceDispatchesCard } from './invoice-dispatches-card';
import type { ApiInvoiceDispatchHistory } from '../types/api-invoice-dispatch';

const mocks = vi.hoisted(() => ({
    historial: {
        envios: [
            { id: 1, destinatario: 'cliente@example.com', asunto: 'Factura FV-1', estado: 'fallido', detalle_error: 'Buzón lleno', enviado_en: null, creado_en: '2026-10-12T10:00:00' },
            { id: 2, destinatario: 'cliente@example.com', asunto: 'Factura FV-1', estado: 'enviado', detalle_error: null, enviado_en: '2026-10-12T10:05:00', creado_en: '2026-10-12T10:05:00' },
        ],
    } satisfies ApiInvoiceDispatchHistory,
}));

vi.mock('../../shared/hooks/use-resource', () => ({
    useResource: () => ({ data: mocks.historial, refetch: () => {} }),
}));

vi.mock('../../shared/hooks/use-send-request', () => ({
    useSendRequest: () => ({ sendRequest: vi.fn() }),
}));

describe('InvoiceDispatchesCard', () => {
    it('con facturacion.enviar ofrece reintentar el envío fallido', () => {
        render(<InvoiceDispatchesCard documentoId={7} canSend />);

        expect(screen.getAllByRole('button', { name: 'Reintentar' })).toHaveLength(1);
    });

    it('sin facturacion.enviar muestra el historial pero no permite reintentar', () => {
        render(<InvoiceDispatchesCard documentoId={7} canSend={false} />);

        expect(screen.getByText('Buzón lleno')).toBeTruthy();
        expect(screen.queryByRole('button', { name: 'Reintentar' })).toBeNull();
    });
});
