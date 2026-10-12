import type { FC } from 'react';

import { useTypeahead } from '../../shared/hooks/use-typeahead';
import { Dropdown } from '@ui/dropdown/components/dropdown';
import { DropdownItem } from '@ui/dropdown/components/dropdown-item';
import { Input } from '@ui/forms/components/input';
import { Search } from 'lucide-react';
import type { ClientSelectorProps } from '../props/client-selector-props';
import type { ApiClient } from '../types/api-client';

export const ClientSelector: FC<ClientSelectorProps> = ({ id, selected, onSelect }) => {
    const { query, setQuery, isOpen, setIsOpen, results, isLoading, reset } = useTypeahead<ApiClient>('/clientes');

    const handleSelect = (client: ApiClient) => {
        onSelect(client);
        reset();
    };

    const displayValue = isOpen
        ? query
        : selected
          ? `${selected.tercero.nombre} — ${selected.tercero.tipo_documento.toUpperCase()} ${selected.tercero.numero_documento}`
          : '';

    return (
        <div className="relative">
            <Input
                id={id}
                name={id}
                placeholder="Busca por nombre o documento…"
                startIcon={<Search className="size-4" />}
                value={displayValue}
                onFocus={() => setIsOpen(true)}
                onChange={(event) => setQuery(event.target.value)}
            />

            <Dropdown isOpen={isOpen} onClose={() => setIsOpen(false)} className="max-h-64 w-full overflow-y-auto custom-scrollbar left-0">
                {isLoading && <div className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">Buscando…</div>}

                {!isLoading && results.length === 0 && (
                    <div className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">Sin resultados.</div>
                )}

                {!isLoading &&
                    results.map((client) => (
                        <DropdownItem key={client.id} onClick={() => handleSelect(client)}>
                            {client.tercero.nombre} — {client.tercero.tipo_documento.toUpperCase()} {client.tercero.numero_documento}
                        </DropdownItem>
                    ))}
            </Dropdown>
        </div>
    );
};
