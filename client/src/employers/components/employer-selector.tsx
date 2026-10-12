import type { FC } from 'react';

import { usePermission } from '../../auth/hooks/use-permission';
import { useTypeahead } from '../../shared/hooks/use-typeahead';
import { Dropdown } from '@ui/dropdown/components/dropdown';
import { DropdownItem } from '@ui/dropdown/components/dropdown-item';
import { Input } from '@ui/forms/components/input';
import { Search } from 'lucide-react';
import type { EmployerSelectorProps } from '../props/employer-selector-props';
import type { ApiEmployer } from '../types/api-employer';

export const EmployerSelector: FC<EmployerSelectorProps> = ({ id, selected, onSelect }) => {
    const { query, setQuery, isOpen, setIsOpen, results, isLoading, reset } = useTypeahead<ApiEmployer>('/empleadores');
    const canList = usePermission('empleadores', 'listar');
    const canCreate = usePermission('empleadores', 'crear');

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
                placeholder={canList ? 'Busca un empleador…' : 'Sin permiso para consultar empleadores'}
                startIcon={<Search className="size-4" />}
                value={displayValue}
                disabled={!canList}
                onFocus={() => setIsOpen(canList)}
                onChange={(event) => setQuery(event.target.value)}
            />

            <Dropdown isOpen={isOpen} onClose={() => setIsOpen(false)} className="max-h-64 w-full overflow-y-auto custom-scrollbar left-0">
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

                {canCreate && (
                    <DropdownItem
                        tag="link"
                        to="/empleadores/crear"
                        className="border-t border-gray-100 font-medium text-brand-600 dark:border-gray-800 dark:text-brand-400"
                    >
                        + Crear empleador
                    </DropdownItem>
                )}
            </Dropdown>
        </div>
    );
};
