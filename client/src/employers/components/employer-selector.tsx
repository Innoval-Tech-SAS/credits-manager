import type { FC } from 'react';

import { useTypeahead } from '../../shared/hooks/use-typeahead';
import { Dropdown } from '@ui/dropdown/components/dropdown';
import { DropdownItem } from '@ui/dropdown/components/dropdown-item';
import { Input } from '@ui/forms/components/input';
import { Search } from 'lucide-react';
import type { EmployerSelectorProps } from '../props/employer-selector-props';
import type { ApiEmployer } from '../types/api-employer';

export const EmployerSelector: FC<EmployerSelectorProps> = ({ id, selected, onSelect }) => {
    const { query, setQuery, isOpen, setIsOpen, results, isLoading, reset } = useTypeahead<ApiEmployer>('/empleadores');

    const handleSelect = (empleador: ApiEmployer) => {
        onSelect(empleador);
        reset();
    };

    const normalizedQuery = query.trim().toLowerCase();

    const displayValue = isOpen ? query : (selected?.nombre ?? '');

    return (
        <div className="relative">
            <Input
                id={id}
                placeholder="Busca un empleador…"
                startIcon={<Search className="size-4" />}
                value={displayValue}
                onFocus={() => setIsOpen(true)}
                onChange={(event) => setQuery(event.target.value)}
            />

            <Dropdown isOpen={isOpen} onClose={() => setIsOpen(false)} className="max-h-64 w-full overflow-y-auto left-0">
                {isLoading && <div className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">Buscando…</div>}

                {!isLoading && results.length === 0 && !normalizedQuery && (
                    <div className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">Escribe para buscar…</div>
                )}

                {!isLoading &&
                    results.map((empleador) => (
                        <DropdownItem key={empleador.id} onClick={() => handleSelect(empleador)}>
                            {empleador.nombre}
                        </DropdownItem>
                    ))}

                <DropdownItem
                    tag="link"
                    to="/empleadores/crear"
                    className="border-t border-gray-100 font-medium text-brand-600 dark:border-gray-800 dark:text-brand-400"
                >
                    + Crear empleador
                </DropdownItem>
            </Dropdown>
        </div>
    );
};
