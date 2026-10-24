import type { FC } from 'react';

import { useResource } from '../../shared/hooks/use-resource';
import type { PaginatedResponse } from '../../shared/types/paginated-response';
import { Select } from '@ui/forms/components/select';
import type { ClientFullSelectProps } from '../props/client-full-select-props';
import type { ApiClient } from '../types/api-client';

export const ClientFullSelect: FC<ClientFullSelectProps> = ({ id, selected, onSelect }) => {
    const { data, isLoading } = useResource<PaginatedResponse<ApiClient>>('/clientes?per_page=100');
    const clientes = data?.data ?? [];

    const options = clientes.map((cliente) => ({
        value: String(cliente.id),
        label: `${cliente.tercero.nombre} — ${cliente.tercero.tipo_documento.toUpperCase()} ${cliente.tercero.numero_documento}`,
    }));

    return (
        <Select
            id={id}
            name={id}
            key={selected?.id ?? 'none'}
            options={options}
            placeholder={isLoading ? 'Cargando clientes…' : 'Selecciona un cliente…'}
            defaultValue={selected ? String(selected.id) : ''}
            onChange={(value) => onSelect(clientes.find((cliente) => String(cliente.id) === value) ?? null)}
        />
    );
};
