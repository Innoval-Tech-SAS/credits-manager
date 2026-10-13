import type { FC } from 'react';
import { X } from 'lucide-react';

import { ClientFullSelect } from '../../clients/components/client-full-select';
import { useResource } from '../../shared/hooks/use-resource';
import type { PaginatedResponse } from '../../shared/types/paginated-response';
import { formatDateEs } from '../../shared/utils/format-date-es';
import { Button } from '@ui/button/components/button';
import { DatePicker } from '@ui/forms/components/date-picker';
import { Label } from '@ui/forms/components/label';
import { Select } from '@ui/forms/components/select';
import { NOTE_CREDIT_REASON_OPTIONS, NOTE_DEBIT_REASON_OPTIONS } from '../constants/invoice-note-reasons';
import type { InvoiceNoteFiltersProps } from '../props/invoice-note-filters-props';
import type { ApiInvoice } from '../types/api-invoice';

export const InvoiceNoteFilters: FC<InvoiceNoteFiltersProps> = ({
    tipo,
    cliente,
    onClienteChange,
    estado,
    onEstadoChange,
    desde,
    onDesdeChange,
    hasta,
    onHastaChange,
    factura,
    onFacturaChange,
    motivo,
    onMotivoChange,
}) => {
    const { data, isLoading: cargandoFacturas } = useResource<PaginatedResponse<ApiInvoice>>(
        cliente ? `/facturacion/documentos?tipo=factura&estado=emitido&cliente_id=${cliente.id}&per_page=100` : null,
    );
    const opcionesFactura = (data?.data ?? [])
        .filter((documento) => documento.numero)
        .map((documento) => ({ value: documento.numero as string, label: `${documento.numero} — ${formatDateEs(documento.fecha)}` }));
    const opcionesMotivo = tipo === 'nota_credito' ? NOTE_CREDIT_REASON_OPTIONS : NOTE_DEBIT_REASON_OPTIONS;

    return (
        <div className="mb-6 rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/3">
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div>
                    <Label htmlFor="nota-cliente">Cliente</Label>
                    <div className="flex items-center gap-2">
                        <div className="flex-1">
                            <ClientFullSelect id="nota-cliente" selected={cliente} onSelect={onClienteChange} />
                        </div>
                        {cliente && (
                            <Button iconOnly variant="outline" startIcon={<X className="size-4" />} onClick={() => onClienteChange(null)}>
                                Quitar cliente
                            </Button>
                        )}
                    </div>
                </div>
                <div>
                    <Label htmlFor="nota-factura">Factura origen</Label>
                    <Select
                        id="nota-factura"
                        name="nota-factura"
                        key={`factura-${cliente?.id ?? 'none'}`}
                        options={opcionesFactura}
                        placeholder={!cliente ? 'Selecciona un cliente primero' : cargandoFacturas ? 'Cargando facturas…' : 'Todas'}
                        placeholderSelectable
                        disabled={!cliente}
                        defaultValue={factura}
                        onChange={onFacturaChange}
                    />
                </div>
                <div>
                    <Label htmlFor="nota-motivo">Motivo</Label>
                    <Select
                        id="nota-motivo"
                        name="nota-motivo"
                        key={`motivo-${tipo}`}
                        options={opcionesMotivo}
                        placeholder="Todos"
                        placeholderSelectable
                        defaultValue={motivo}
                        onChange={onMotivoChange}
                    />
                </div>
                <div>
                    <Label htmlFor="nota-estado">Estado</Label>
                    <Select
                        id="nota-estado"
                        options={[
                            { value: 'borrador', label: 'Borrador' },
                            { value: 'emitido', label: 'Emitido' },
                        ]}
                        placeholder="Todos"
                        placeholderSelectable
                        defaultValue={estado}
                        onChange={onEstadoChange}
                    />
                </div>
                <div>
                    <Label htmlFor="nota-desde">Fecha desde</Label>
                    <DatePicker id="nota-desde" value={desde} onChange={onDesdeChange} placeholder="Cualquier fecha" />
                </div>
                <div>
                    <Label htmlFor="nota-hasta">Fecha hasta</Label>
                    <DatePicker id="nota-hasta" value={hasta} onChange={onHastaChange} placeholder="Cualquier fecha" />
                </div>
            </div>
        </div>
    );
};
