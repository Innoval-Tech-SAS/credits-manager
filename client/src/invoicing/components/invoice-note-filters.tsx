import type { FC } from 'react';

import { ClientFullSelect } from '../../clients/components/client-full-select';
import { Button } from '@ui/button/components/button';
import { DatePicker } from '@ui/forms/components/date-picker';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { Select } from '@ui/forms/components/select';
import type { InvoiceNoteFiltersProps } from '../props/invoice-note-filters-props';

export const InvoiceNoteFilters: FC<InvoiceNoteFiltersProps> = ({
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
}) => (
    <div className="mb-6 rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/3">
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div>
                <Label htmlFor="nota-cliente">Cliente</Label>
                <ClientFullSelect id="nota-cliente" selected={cliente} onSelect={onClienteChange} />
                {cliente && (
                    <Button variant="outline" size="sm" className="mt-2" onClick={() => onClienteChange(null)}>
                        Quitar cliente
                    </Button>
                )}
            </div>
            <div>
                <Label htmlFor="nota-factura">Factura origen</Label>
                <Input
                    id="nota-factura"
                    name="nota-factura"
                    placeholder="Número de factura"
                    value={factura}
                    onChange={(event) => onFacturaChange(event.target.value)}
                />
            </div>
            <div>
                <Label htmlFor="nota-motivo">Motivo</Label>
                <Input
                    id="nota-motivo"
                    name="nota-motivo"
                    placeholder="Buscar en el motivo"
                    value={motivo}
                    onChange={(event) => onMotivoChange(event.target.value)}
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
