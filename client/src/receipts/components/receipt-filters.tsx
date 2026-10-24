import type { FC } from 'react';

import { Button } from '@ui/button/components/button';
import { DatePicker } from '@ui/forms/components/date-picker';
import { Label } from '@ui/forms/components/label';
import { Select } from '@ui/forms/components/select';
import { ThirdPartySelector } from '../../third-parties/components/third-party-selector';
import type { ReceiptFiltersProps } from '../props/receipt-filters-props';

export const ReceiptFilters: FC<ReceiptFiltersProps> = ({
    tercero,
    onTerceroChange,
    estado,
    onEstadoChange,
    desde,
    onDesdeChange,
    hasta,
    onHastaChange,
}) => (
    <div className="mb-6 rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/3">
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div>
                <Label htmlFor="recibo-tercero">Tercero</Label>
                <ThirdPartySelector id="recibo-tercero" selected={tercero} onSelect={onTerceroChange} />
                {tercero && (
                    <Button variant="outline" size="sm" className="mt-2" onClick={() => onTerceroChange(null)}>
                        Quitar tercero
                    </Button>
                )}
            </div>
            <div>
                <Label htmlFor="recibo-estado">Estado</Label>
                <Select
                    id="recibo-estado"
                    options={[
                        { value: 'emitido', label: 'Emitido' },
                        { value: 'anulado', label: 'Anulado' },
                    ]}
                    placeholder="Todos"
                    placeholderSelectable
                    defaultValue={estado}
                    onChange={onEstadoChange}
                />
            </div>
            <div>
                <Label htmlFor="recibo-desde">Pago desde</Label>
                <DatePicker id="recibo-desde" value={desde} onChange={onDesdeChange} placeholder="Cualquier fecha" />
            </div>
            <div>
                <Label htmlFor="recibo-hasta">Pago hasta</Label>
                <DatePicker id="recibo-hasta" value={hasta} onChange={onHastaChange} placeholder="Cualquier fecha" />
            </div>
        </div>
    </div>
);
