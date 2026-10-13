import type { ApiClient } from '../../clients/types/api-client';

export type InvoiceNoteFiltersProps = {
    tipo: 'nota_credito' | 'nota_debito';
    cliente: ApiClient | null;
    onClienteChange: (cliente: ApiClient | null) => void;
    estado: string;
    onEstadoChange: (estado: string) => void;
    desde: string;
    onDesdeChange: (desde: string) => void;
    hasta: string;
    onHastaChange: (hasta: string) => void;
    factura: string;
    onFacturaChange: (factura: string) => void;
    motivo: string;
    onMotivoChange: (motivo: string) => void;
};
