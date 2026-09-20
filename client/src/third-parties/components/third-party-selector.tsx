import type { FC } from 'react';

import { useTypeahead } from '../../shared/hooks/use-typeahead';
import { Dropdown } from '@ui/dropdown/components/dropdown';
import { DropdownItem } from '@ui/dropdown/components/dropdown-item';
import { Input } from '@ui/forms/components/input';
import { Search } from 'lucide-react';
import type { ThirdPartySelectorProps } from '../props/third-party-selector-props';
import type { ApiThirdParty } from '../types/api-third-party';

export const ThirdPartySelector: FC<ThirdPartySelectorProps> = ({ id, selected, onSelect }) => {
    const { query, setQuery, isOpen, setIsOpen, results, isLoading, reset } = useTypeahead<ApiThirdParty>('/terceros');

    const handleSelect = (tercero: ApiThirdParty) => {
        onSelect(tercero);
        reset();
    };

    const displayValue = isOpen ? query : (selected?.nombre ?? '');

    return (
        <div className="relative">
            <Input
                id={id}
                placeholder="Buscar tercero…"
                startIcon={<Search className="size-4" />}
                value={displayValue}
                onFocus={() => setIsOpen(true)}
                onChange={(event) => setQuery(event.target.value)}
            />

            <Dropdown isOpen={isOpen} onClose={() => setIsOpen(false)} className="max-h-64 w-full overflow-y-auto left-0">
                {isLoading && <div className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">Buscando…</div>}

                {!isLoading && results.length === 0 && (
                    <div className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                        {query ? 'Sin resultados.' : 'Escribe para buscar…'}
                    </div>
                )}

                {!isLoading &&
                    results.map((tercero) => (
                        <DropdownItem key={tercero.id} onClick={() => handleSelect(tercero)}>
                            {tercero.nombre}
                        </DropdownItem>
                    ))}
            </Dropdown>
        </div>
    );
};
